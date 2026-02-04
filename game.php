<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$db = new Database();
$pdo = $db->connect();

// Получаем ID игры из URL
$game_id = $_GET['name'] ?? '';

// Если ID не указан или невалиден - редирект
if ($game_id <= 0) {
    header('Location: /explore');
    exit();
}

// Получаем информацию об игре
$gameController = new Game();
$game = $gameController->getGameById($game_id);

// Иннформация сколько раз скачали
$stmt = $db->connect()->prepare("SELECT * FROM library where game_id = ?");
$stmt->execute([$game_id]);
$downloaded = count($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $db->connect()->prepare("SELECT * FROM studios where name = ?");
$stmt->execute([$game['studio_name']]);
$studio_payment_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    header('Location: /explore');
    exit();
}

if (empty($game['status']) || strtolower($game['status']) !== 'published') {
    header('Location: /explore');
    exit();
}

$userRating = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userId = $_SESSION['USERDATA']['id'];
    $userRating = $gameController->userHasRated($game_id, $userId) ?? 0;
}

$screenshots = json_decode($game['screenshots'], true) ?: [];

// Получаем особенности
$features = json_decode($game['features'], true) ?: [];

// Получаем системные требования
$requirements = json_decode($game['requirements'], true) ?: [];

// Получаем достижения
$achievements = json_decode($game['achievements'], true) ?: [];

// Получаем бейджи
$badges = !empty($game['badges']) ? explode(',', $game['badges']) : [];

// Получаем платформы
$platforms = !empty($game['platforms']) ? explode(',', $game['platforms']) : [];

function formatFileSize($bytes)
{
    if ($bytes < 1024) {
        return $bytes . ' Б';
    } elseif ($bytes < 1048576) { // 1024 * 1024
        return round($bytes / 1024, 2) . ' КБ';
    } elseif ($bytes < 1073741824) { // 1024^3
        return round($bytes / 1048576, 2) . ' МБ';
    } else {
        return round($bytes / 1073741824, 2) . ' ГБ';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - <?= htmlspecialchars($game['name']) ?></title>
    <link rel="stylesheet" href="/swad/css/gamepage.css">
    <!-- TODO: GAME's icon -->
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <style>
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .lightbox .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            color: white;
            cursor: pointer;
            user-select: none;
            padding: 0 15px;
            z-index: 10001;
        }

        .lightbox .arrow-left {
            left: 10px;
        }

        .lightbox .arrow-right {
            right: 10px;
        }

        .review-form {
            margin-top: 30px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .review-form h2 {
            margin-bottom: 10px;
            font-size: 1.5rem;
            color: #fff;
        }

        .review-form textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(0, 0, 0, 0.4);
            color: #fff;
            padding: 10px;
            font-size: 1rem;
            resize: vertical;
        }

        .review-form select {
            border-radius: 10px;
            padding: 5px 10px;
            background: rgba(0, 0, 0, 0.4);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .review-form button {
            align-self: flex-start;
            padding: 8px 20px;
            border: none;
            border-radius: 10px;
            background-color: #74155d;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .review-form button:hover {
            background-color: #14041d;
            color: #fff;
        }

        #review-stars span {
            font-size: 24px;
            color: #666;
            cursor: pointer;
            margin-right: 5px;
            transition: color 0.2s;
        }

        #review-stars span:hover,
        #review-stars span.highlighted {
            color: #ffcc00;
        }
    </style>
    <script src="/swad/js/CartManager.js"></script>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="game-hero">
            <!-- Баннер игры -->
            <div class="game-banner" style="background-image: url('<?= !empty($game['banner_url']) ? htmlspecialchars($game['banner_url']) : '' ?>')"></div>

            <div class="container">
                <div class="game-content">
                    <div class="game-main">
                        <div class="game-header">
                            <div class="game-logo">
                                <!-- Обложка игры -->
                                <img class="game-logo" src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/swad/static/img/hg-icon.jpg' ?>" alt="<?= htmlspecialchars($game['name']) ?>">
                            </div>
                            <div class="game-info-header">
                                <h1><?= htmlspecialchars($game['name']) ?></h1>

                                <?php if (!empty($studio_payment_data['donate_link'])): ?>
                                    <h3>💰<a href="<?= $studio_payment_data['donate_link'] ?>" style="color: #ffffff;" target="_blank">Задонатить разработчику</a></h3>
                                <?php endif; ?>

                                <!-- Бейджи игры -->
                                <div class="game-badges">
                                    <?php foreach ($badges as $badge): ?>
                                        <div class="game-badge"><?= htmlspecialchars(trim($badge)) ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Статистика игры -->
                                <div class="game-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?= htmlspecialchars($game['GQI']) ?>/100</div>
                                        <div class="stat-label">GQI</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= date('d.m.Y', strtotime($game['release_date'])) ?></div>
                                        <div class="stat-label">Дата выпуска</div>
                                    </div>
                                    <?php
                                    $ratingData = $gameController->getAverageRating($game_id);
                                    ?>
                                    <div class="stat-item">
                                        <?php if ($ratingData['count'] > 0): ?>
                                            <div class="stat-value"><?= $ratingData['avg'] ?>/10 </div>
                                            <div class="stat-label">Оценили: <?= $ratingData['count'] ?></div>
                                        <?php else: ?>
                                            <div class="stat-value">???</div>
                                            <div class="stat-label">Ещё нет оценок. Будьте первыми.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Описание игры -->
                        <div class="game-description">
                            <p><?= nl2br(htmlspecialchars($game['description'])) ?></p>
                        </div>

                        <!-- <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                            <div class="rating-section" style="margin-top: 20px;">
                                <h2>Ваша оценка игре</h2>
                                <div id="rating-stars" data-game-id="<?= $game_id ?>" data-user-rating="<?= $userRating ?>"></div>
                            </div>
                        <?php else: ?>
                            <div class="rating-section" style="margin-top: 20px; opacity: 0.6;">
                                <h2>Оценить игру</h2>
                                <p>Войдите в аккаунт, чтобы поставить оценку.</p>
                            </div>
                        <?php endif; ?> -->

                        <!-- Особенности игры -->
                        <?php if (!empty($features)): ?>
                            <div class="game-features">
                                <h2>Особенности игры</h2>
                                <div class="features-list">
                                    <?php foreach ($features as $feature): ?>
                                        <div class="feature-item">
                                            <div class="feature-icon"><?= htmlspecialchars($feature['icon']) ?></div>
                                            <div>
                                                <h3><?= htmlspecialchars($feature['title']) ?></h3>
                                                <p><?= htmlspecialchars($feature['description']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Трейлер игры -->
                        <?php if (!empty($game['trailer_url'])): ?>
                            <h2>Трейлер</h2>
                            <div class="trailer-container">
                                <iframe src="<?= htmlspecialchars($game['trailer_url']) ?>" width="640" height="360" frameborder="0" allowfullscreen="1" allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>
                            </div>
                        <?php endif; ?>

                        <!-- Скриншоты игры -->
                        <?php if (!empty($screenshots)): ?>
                            <h2>Скриншоты</h2>
                            <div class="screenshots-grid">
                                <?php foreach ($screenshots as $screenshot): ?>
                                    <div class="screenshot"
                                        style="background: url('<?= htmlspecialchars($screenshot['path']) ?>') no-repeat center center / cover;"
                                        data-fullsize="<?= htmlspecialchars($screenshot['path']) ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Системные требования -->
                        <?php if (!empty($requirements)): ?>
                            <div class="system-requirements">
                                <h2>Системные требования</h2>
                                <div class="requirements-grid">
                                    <?php foreach ($requirements as $requirement): ?>
                                        <div class="requirement-item">
                                            <div class="requirement-label"><?= htmlspecialchars($requirement['label']) ?></div>
                                            <div class="requirement-value"><?= htmlspecialchars($requirement['value']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="reviews-section">
                            <h2>Отзывы игроков</h2>
                            <div id="reviews-container">
                                <!-- Здесь JS добавляет отзывы -->
                                <p>Загрузка отзывов...</p>
                            </div>
                            <?php
                            $userHasGame = false;
                            $db = new Database();
                            $pdo = $db->connect();
                            if (!empty($_SESSION['USERDATA']['id'])) {
                                $stmt = $pdo->prepare("SELECT id FROM library WHERE player_id = ? AND game_id = ?");
                                $stmt->execute([$_SESSION['USERDATA']['id'], $game_id]);
                                $userHasGame = (bool) $stmt->fetch();
                            }
                            ?>
                            <?php if (!empty($_SESSION['USERDATA']['id']) && $userHasGame): ?>
                                <div class="review-form" style="margin-top: 30px;">
                                    <h2>Оставить отзыв</h2>
                                    <textarea id="review-text" placeholder="Напишите ваш отзыв..." rows="4"></textarea>
                                    <div style="margin-top:10px;">
                                        <label>Ваша оценка: </label>
                                        <div id="review-stars" style="display:inline-block;"></div>
                                    </div>
                                    <button class="btn" style="margin-top:10px;" id="submit-review">Отправить</button>
                                </div>
                            <?php elseif (!empty($_SESSION['USERDATA']['id'])): ?>
                                <p style="color: orange; margin-top: 20px;">Сначала скачайте игру, чтобы оставить отзыв.</p>
                            <?php else: ?>
                                <p style="color: orange; margin-top: 20px;">Войдите в аккаунт, чтобы скачать игру и оставить отзыв.</p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <div class="game-sidebar">
                        <div class="purchase-section">
                            <?php if ($game['price'] > 0): ?>
                                <div class="game-price"><?= number_format($game['price'], 0, ',', ' ') ?> ₽</div>
                                <div class="cart-controls" id="cart-controls-<?= $game_id ?>">
                                    <!-- Будет заполнено JavaScript -->
                                </div>

                                <?php
                                // 2.
                                // Оплата заданной суммы с выбором валюты на сайте ROBOKASSA
                                // Payment of the set sum with a choice of currency on site ROBOKASSA

                                // регистрационная информация (логин, пароль #1)
                                // registration info (login, password #1)
                                $mrh_login = $studio_payment_data['merchant_login'];
                                $mrh_pass1 = $studio_payment_data['merchant_password'];

                                // номер заказа
                                // number of order
                                $inv_id = time();
                                $IsTest = 1;

                                // описание заказа
                                // order description
                                $inv_desc = $game['description'];

                                // сумма заказа
                                // sum of order
                                $out_summ = $game['price'];

                                // id игры
                                // code of goods
                                $shp_item = $game_id;

                                // предлагаемая валюта платежа
                                // default payment e-currency
                                $in_curr = "ru";

                                // язык
                                // language
                                $culture = "ru";

                                $encrypted_mrh_passwd = bin2hex(xorStrings($mrh_pass1, PASSWD_FOR_PASSWDS));

                                // формирование подписи
                                // generate signature
                                // echo bin2hex($encrypted_mrh_passwd);
                                $crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1:Shp_enc_mrh_pass=$encrypted_mrh_passwd:Shp_item=$shp_item");

                                // форма оплаты товара
                                // payment form
                                print '<script src="https://integrationjs.tbank.ru/integration.js"></script>
                                        <button onclick="pay(1)">Оплатить</button>';
                                ?>

                                <!-- <button class="btn" style="width: 100%; margin-bottom: 15px;" onclick="location.href='/checkout'">Купить сейчас</button> -->

                                <div style="margin-top: 20px; font-size: 0.9rem; opacity: 0.8;">
                                    <?php if ($game['in_subscription']): ?>
                                        <p>✔️ Есть в подписке</p><br>

                                    <?php endif; ?>
                                    <!-- <p>✔️ Высокий рейтинг</p> -->

                                    <?php if (!empty($game['game_zip_size'])): ?>
                                        <div style="font-size: 0.9rem; opacity: 0.8;">
                                            Размер: <?= htmlspecialchars(formatFileSize((int)$game['game_zip_size'])) ?>
                                            <br>
                                            Купили: <?= $downloaded ?> раз(а)
                                        </div>
                                    <?php else: ?>
                                        <p style="color: orange;">Файл игры пока не загружен</p>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <!-- Бесплатная игра -->
                                <div style="text-align: center;">
                                    <div class="game-price" style="font-size: 1.4rem; color: #00ff99; margin-bottom: 10px;">
                                        Бесплатно
                                    </div>

                                    <?php if (!empty($game['game_zip_url'])): ?>
                                        <?php if ($game['platforms'] == 'web'): ?>
                                            <button class="btn" style="width: 100%; margin-bottom: 10px;"
                                                onclick="window.location.href='/webplayer?id=<?= $game_id ?>'">
                                                Запустить игру в браузере
                                            </button>
                                        <?php else: ?>
                                            <button class="btn" style="width: 100%; margin-bottom: 10px;"
                                                onclick="window.location.href='/swad/controllers/download_game.php?game_id=<?= $game_id ?>'">
                                                Скачать игру
                                            </button>

                                            <button onclick="downloadGame(123)">Скачать игру</button>

                                            <script>
                                                function downloadGame(gameId) {
                                                    fetch(`http://127.0.0.1:5000/download_game?game_id=${gameId}`)
                                                        .then(r => r.json())
                                                        .then(res => {
                                                            if (res.ok) {
                                                                alert("Игра скачана и распакована!");
                                                            } else {
                                                                alert("Ошибка: " + res.error);
                                                            }
                                                        }).catch(err => alert("Не удалось обратиться к лаунчеру"));
                                                }
                                            </script>

                                            <button onclick="launchGame('ach.exe')">Запустить игру</button>

                                            <script>
                                                function launchGame(path) {
                                                    fetch(`http://127.0.0.1:5000/launch_game?path=${encodeURIComponent(path)}`)
                                                        .then(r => r.json())
                                                        .then(res => {
                                                            if (res.ok) {
                                                                alert("Игра запущена!");
                                                            } else {
                                                                alert("Ошибка: " + res.error);
                                                            }
                                                        }).catch(err => alert("Не удалось обратиться к лаунчеру"));
                                                }
                                            </script>


                                        <?php endif; ?>

                                        <?php if (!empty($game['game_zip_size'])): ?>
                                            <?php if ($game['platforms'] == 'web'): ?>
                                                <div style="font-size: 0.9rem; opacity: 0.8;">
                                                    Это веб игра. Она не требует скачивания.
                                                    <br>
                                                    <!-- Сыграли: <?= $downloaded ?> раз(а) -->
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 0.9rem; opacity: 0.8;">
                                                    Размер: <?= htmlspecialchars(formatFileSize((int)$game['game_zip_size'])) ?>
                                                    <br>
                                                    Скачали: <?= $downloaded ?> раз(а)
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p style="color: orange;">Файл игры пока не загружен</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>


                        <!-- Информация о разработчике -->
                        <div class="developer-info" onclick="location.href='/d/<?= htmlspecialchars($game['studio_slug']) ?>'" style="cursor: pointer;">
                            <div class="developer-logo">🏢</div>
                            <div>
                                <h3><?= htmlspecialchars($game['studio_name']) ?></h3>
                                <p>Основана в <?= date('Y', strtotime($game['studio_founded'])) ?></p>
                            </div>
                        </div>

                        <button class="btn btn-secondary" style="width: 100%; margin-bottom: 20px;" onclick="location.href='/d/<?= htmlspecialchars($game['studio_slug']) ?>'">
                            Все игры разработчика
                        </button>

                        <!-- Кнопка для просмотра оферты -->
                        <button class="btn btn-secondary" style="width: 100%; margin-bottom: 20px;" onclick="showOfferModal()">
                            Оферта разработчика
                        </button>

                        <!-- Модальное окно с офертой -->
                        <div id="offerModal" class="modal">
                            <div class="modal-content">
                                <span class="close" onclick="closeOfferModal()">&times;</span>

                                <div class="offer-content">
                                    <h2>ПУБЛИЧНАЯ ОФЕРТА</h2>

                                    <div>
                                        <?php $stpd = $studio_payment_data; ?>
                                        <p><strong>г. <?= htmlspecialchars($stpd['city'] ?? 'Москва') ?></strong></p>
                                        <p><strong><?= date('d.m.Y', strtotime($game['offer_date'] ?? 'now')) ?></strong></p>
                                    </div>
                                    <div>
                                        <p><strong><?= htmlspecialchars($stpd['name']) ?></strong></p>
                                        <p>ИНН: <?= htmlspecialchars($stpd['INN'] ?? 'Разработчик не указал ИНН') ?></p>
                                        <p>Юридический адрес: <?= htmlspecialchars($stpd['city'] ?? 'Разработчик не указал адрес') ?></p>
                                        <p>Расчетный счет: <?= htmlspecialchars($stpd['acc_num'] ?? 'Разработчик не указал расчетный счет') ?></p>
                                        <p>Банк: <?= htmlspecialchars($stpd['bank_name'] ?? 'Разработчик не указал банк') ?></p>
                                        <p>БИК: <?= htmlspecialchars($stpd['BIC'] ?? 'Разработчик не указал БИК') ?></p>
                                        <!-- <p>Корр. счет: <?= htmlspecialchars($stpd['studio_correspondent_account'] ?? 'Разработчик не указал корр. счет') ?></p> -->
                                    </div>

                                    <div>
                                        <h3>1. ПРЕДМЕТ ОФЕРТЫ</h3>
                                        <p>1.1. <?= htmlspecialchars($game['studio_name']) ?> (далее — «Продавец») предлагает заключить договор розничной купли-продажи цифрового товара (далее — «Договор») на изложенных ниже условиях.</p>
                                        <p>1.2. Цифровым товаром признается игра «<?= htmlspecialchars($game['name']) ?>» в цифровой форме, не имеющая материального носителя.</p>
                                    </div>

                                    <div>
                                        <h3>2. МОМЕНТ ЗАКЛЮЧЕНИЯ ДОГОВОРА</h3>
                                        <p>2.1. Текст данного Договора является публичной офертой.</p>
                                        <p>2.2. Акцептом оферты является совершение Покупателем действий, направленных на осуществление платежа за Товар.</p>
                                        <p>2.3. Акцепт оферты означает, что Покупатель согласен со всеми положениями настоящего Договора.</p>
                                    </div>

                                    <div>
                                        <h3>3. ЦЕНА ТОВАРА И ПОРЯДОК РАСЧЕТОВ</h3>
                                        <p>3.1. Цена Товара указывается на странице Товара в момент оформления заказа.</p>
                                        <p>3.2. Расчеты между сторонами осуществляются с использованием платежного сервиса Робокасса.</p>
                                        <p>3.3. Моментом оплаты считается поступление денежных средств на расчетный счет Продавца.</p>
                                    </div>

                                    <div>
                                        <h3>4. ПЕРЕДАЧА ТОВАРА</h3>
                                        <p>4.1. Товар передается Покупателю в электронной форме путем предоставления доступа к скачиванию файлов или активации лицензионного ключа.</p>
                                        <p>4.2. Моментом исполнения обязательств Продавца по передаче Товара считается момент предоставления доступа к Товару.</p>
                                    </div>

                                    <div>
                                        <h3>5. ВОЗВРАТ ТОВАРА</h3>
                                        <p>5.1. В соответствии с пунктом 4 статьи 26.1 Закона «О защите прав потребителей» цифровые товары надлежащего качества возврату и обмену не подлежат.</p>
                                        <p>5.2. Возврат денежных средств осуществляется в случае технической невозможности предоставить оплаченный Товар.</p>
                                    </div>

                                    <div>
                                        <h3>6. ЗАКЛЮЧИТЕЛЬНЫЕ ПОЛОЖЕНИЯ</h3>
                                        <p>6.1. Продавец вправе в одностороннем порядке изменять условия оферты.</p>
                                        <p>6.2. К отношениям между Покупателем и Продавцом применяется право Российской Федерации.</p>
                                    </div>

                                    <div>
                                        <h3>7. РЕКВИЗИТЫ ПРОДАВЦА</h3>
                                        <p><strong><?= htmlspecialchars($game['studio_name']) ?></strong></p>
                                        <p>ИНН: <?= htmlspecialchars($stpd['INN'] ?? 'укажите ИНН') ?></p>
                                        <p>Адрес: <?= htmlspecialchars($stpd['city'] . ", " . $stpd['country'] ?? 'укажите адрес') ?></p>
                                        <p>Email: <?= htmlspecialchars($stpd['contact_email'] ?? 'укажите email') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            function showOfferModal() {
                                document.getElementById('offerModal').style.display = 'block';
                            }

                            function closeOfferModal() {
                                document.getElementById('offerModal').style.display = 'none';
                            }

                            // Закрытие модального окна при клике вне его
                            window.onclick = function(event) {
                                const modal = document.getElementById('offerModal');
                                if (event.target == modal) {
                                    closeOfferModal();
                                }
                            }
                        </script>

                        <!-- Дополнительная информация -->
                        <div style="background: rgba(255,255,255,0.1); border-radius: 15px; padding: 20px;">
                            <h3>Информация о игре</h3>
                            <div style="margin-top: 15px;">
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Жанры:</span>
                                    <span><?= htmlspecialchars($game['genre']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Платформы:</span>
                                    <span>
                                        <?php
                                        $platform_names = [];
                                        foreach ($platforms as $platform) {
                                            switch ($platform) {
                                                case 'windows':
                                                    $platform_names[] = 'Windows';
                                                    break;
                                                case 'linux':
                                                    $platform_names[] = 'Linux';
                                                    break;
                                                case 'macos':
                                                    $platform_names[] = 'MacOS';
                                                    break;
                                                case 'android':
                                                    $platform_names[] = 'Android';
                                                    break;
                                                case 'web':
                                                    $platform_names[] = 'Web';
                                                    break;
                                                default:
                                                    $platform_names[] = ucfirst($platform);
                                            }
                                        }
                                        echo htmlspecialchars(implode(', ', $platform_names));
                                        ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Языки:</span>
                                    <span><?= htmlspecialchars($game['languages']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                                    <span>Возрастной рейтинг:</span>
                                    <span><?= htmlspecialchars($game['age_rating']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Достижения -->
                        <?php if (!empty($achievements)): ?>
                            <div style="margin-top: 30px; background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px;">
                                <h3>Достижения</h3>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px;">
                                    <?php foreach ($achievements as $achievement): ?>
                                        <div style="text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                            <div style="font-size: 2rem;"><?= htmlspecialchars($achievement['icon']) ?></div>
                                            <div style="font-size: 0.9rem;"><?= htmlspecialchars($achievement['title']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script src="https://integrationjs.tbank.ru/integration.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация элементов при загрузке
            const animateElements = [
                ...document.querySelectorAll('.game-logo, .stat-item'),
                ...document.querySelectorAll('.screenshot'),
                ...document.querySelectorAll('.feature-item'),
                ...document.querySelectorAll('.review-card')
            ];

            animateElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `all 0.5s ease ${index * 0.1}s`;

                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100);
            });

            // Лайтбокс для скриншотов
            const screenshots = document.querySelectorAll('.screenshot');
            screenshots.forEach(screenshot => {
                screenshot.addEventListener('click', function() {
                    const fullsizeUrl = this.dataset.fullsize;
                    const lightbox = document.createElement('div');
                    lightbox.className = 'lightbox';
                    lightbox.innerHTML = `<img src="${fullsizeUrl}" alt="Full size screenshot">`;

                    lightbox.addEventListener('click', function() {
                        document.body.removeChild(lightbox);
                    });

                    document.body.appendChild(lightbox);
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const gameId = <?= $game_id ?>;
            window.gameCartManager = new GameCartManager(gameId);
        });

        document.addEventListener('DOMContentLoaded', () => {
            const ratingContainer = document.getElementById('rating-stars');
            if (!ratingContainer) return;

            const gameId = ratingContainer.dataset.gameId;
            for (let i = 1; i <= 10; i++) {
                const star = document.createElement('span');
                star.textContent = '★';
                star.style.cursor = 'pointer';
                star.style.fontSize = '24px';
                star.style.color = '#666';
                star.dataset.value = i;
                star.addEventListener('mouseover', () => highlightStars(i));
                star.addEventListener('mouseout', resetStars);
                star.addEventListener('click', () => submitRating(i));
                ratingContainer.appendChild(star);
            }

            function highlightStars(n) {
                document.querySelectorAll('#rating-stars span').forEach((s, idx) => {
                    s.style.color = idx < n ? '#ffcc00' : '#666';
                });
            }

            function resetStars() {
                document.querySelectorAll('#rating-stars span').forEach(s => s.style.color = '#666');
            }

            function submitRating(rating) {
                fetch('/swad/controllers/rate_game.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `game_id=${gameId}&rating=${rating}&devEmail=<?= $stpd['contact_email'] ?>`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // alert(`Спасибо! Ваша оценка: ${rating}`);
                            location.reload();
                        } else {
                            alert('Ошибка: ' + data.error);
                        }
                    });
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const gameId = <?= (int)$game_id ?>;
            const userId = <?= $_SESSION['USERDATA']['id'] ?? 0 ?>;
            const reviewsContainer = document.getElementById('reviews-container');
            const reviewForm = document.querySelector('.review-form');
            const reviewText = document.getElementById('review-text');
            const reviewStars = document.getElementById('review-stars');
            const submitBtn = document.getElementById('submit-review');

            if (!reviewsContainer) return;

            console.log('[reviews] start', {
                gameId
            });

            fetch(`/swad/controllers/get_reviews.php?game_id=${gameId}`, {
                    credentials: 'same-origin'
                })
                .then(async (res) => {
                    console.log('[reviews] status', res.status, 'url', res.url);
                    const text = await res.text();
                    console.log('[reviews] raw response:', text.slice(0, 300));

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('JSON parse failed. Response is not JSON.');
                    }
                    return data;
                })
                .then((data) => {
                    console.log('[reviews] json', data);

                    if (!data.success) {
                        reviewsContainer.innerHTML = '<p>Не удалось загрузить отзывы.</p>';
                        return;
                    }

                    const reviews = Array.isArray(data.reviews) ? data.reviews : [];
                    if (reviews.length === 0) {
                        reviewsContainer.innerHTML = '<p>Отзывы пока отсутствуют. Будьте первым!</p>';
                    } else {
                        reviewsContainer.innerHTML = '';
                        reviews.forEach((review) => {
                            const div = document.createElement('div');
                            div.className = 'review-card';
                            div.innerHTML = `
                    <div class="review-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="review-author" style="display:flex; gap:10px; align-items:center;">
                            <div class="author-avatar" style="width:50px; height:50px; overflow:hidden; border-radius:50%;">
                                <img style="width:100%; height:100%; object-fit:cover;" src="${review.profile_picture || '/swad/static/img/logo.svg'}">
                            </div>
                            <div>
                                <h3>${escapeHtml(review.username || 'Аноним')}</h3>
                                <div>★ ${review.rating}</div>
                            </div>
                        </div>
                        <div class="review-date" style="font-size:0.8rem; opacity:0.7;">
                            ${new Date(review.created_at).toLocaleString('ru-RU')}
                        </div>
                    </div>
                    <p style="margin-top:10px;">${escapeHtml(review.text)}</p>
                `;

                            // Добавляем ответ разработчика
                            if (review.developer_reply) {
                                div.innerHTML += `
                        <div class="developer-reply" style="margin-top:10px; padding:10px; background: rgba(255,255,255,0.05); border-left: 3px solid #74155d;">
                            <strong><svg style='vertical-align: middle;'
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#fff"
                                    stroke-width="1"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    >
                                    <path d="M5 7.2a2.2 2.2 0 0 1 2.2 -2.2h1a2.2 2.2 0 0 0 1.55 -.64l.7 -.7a2.2 2.2 0 0 1 3.12 0l.7 .7c.412 .41 .97 .64 1.55 .64h1a2.2 2.2 0 0 1 2.2 2.2v1c0 .58 .23 1.138 .64 1.55l.7 .7a2.2 2.2 0 0 1 0 3.12l-.7 .7a2.2 2.2 0 0 0 -.64 1.55v1a2.2 2.2 0 0 1 -2.2 2.2h-1a2.2 2.2 0 0 0 -1.55 .64l-.7 .7a2.2 2.2 0 0 1 -3.12 0l-.7 -.7a2.2 2.2 0 0 0 -1.55 -.64h-1a2.2 2.2 0 0 1 -2.2 -2.2v-1a2.2 2.2 0 0 0 -.64 -1.55l-.7 -.7a2.2 2.2 0 0 1 0 -3.12l.7 -.7a2.2 2.2 0 0 0 .64 -1.55v-1" />
                                    <path d="M9 12l2 2l4 -4" />
                                    </svg>
                                    Официальный ответ разработчика:</strong>
                            <p>${escapeHtml(review.developer_reply)}</p>
                            <div style="font-size:0.8rem; opacity:0.7;">${new Date(review.developer_reply_created_at).toLocaleString('ru-RU')}</div>
                        </div>
                    `;
                            }

                            reviewsContainer.appendChild(div);
                        });
                    }

                    // Проверяем, есть ли отзыв текущего пользователя
                    if (userId && reviewForm && reviewText && reviewStars) {
                        const userReview = reviews.find(r => r.user_id == userId);
                        if (userReview) {
                            reviewText.value = userReview.text;
                            selectedRating = userReview.rating;

                            // Добавляем скрытое поле review-id
                            let reviewIdInput = document.createElement('input');
                            reviewIdInput.type = 'hidden';
                            reviewIdInput.id = 'review-id';
                            reviewIdInput.value = userReview.id;
                            reviewForm.appendChild(reviewIdInput);
                        }
                    }

                    // Инициализация звёздочек
                    if (reviewStars) {
                        reviewStars.innerHTML = '';
                        let stars = [];
                        let selectedRating = 10;
                        for (let i = 1; i <= 10; i++) {
                            const star = document.createElement('span');
                            star.textContent = '★';
                            star.dataset.value = i;
                            star.style.cursor = 'pointer';
                            star.addEventListener('mouseover', () => highlightStars(i));
                            star.addEventListener('mouseout', () => highlightStars(selectedRating));
                            star.addEventListener('click', () => {
                                selectedRating = i;
                                highlightStars(selectedRating);
                            });
                            reviewStars.appendChild(star);
                            stars.push(star);
                        }

                        function highlightStars(n) {
                            stars.forEach((s, idx) => s.classList.toggle('highlighted', idx < n));
                        }

                        highlightStars(selectedRating);
                    }

                    // Отправка/редактирование отзыва
                    if (submitBtn) {
                        submitBtn.addEventListener('click', () => {
                            const text = reviewText.value.trim();
                            if (!text) {
                                alert('Введите текст отзыва!');
                                return;
                            }

                            const reviewIdEl = document.getElementById('review-id');
                            const isEdit = reviewIdEl && reviewIdEl.value;

                            const url = isEdit ? '/swad/controllers/update_review.php' : '/swad/controllers/submit_review.php';
                            const body = isEdit ?
                                `review_id=${encodeURIComponent(reviewIdEl.value)}&rating=${selectedRating}&text=${encodeURIComponent(text)}` :
                                `game_id=<?= $game_id ?>&rating=${selectedRating}&text=${encodeURIComponent(text)}`;

                            fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) location.reload();
                                    else alert('Ошибка: ' + (data.error || data.message || 'unknown'));
                                })
                                .catch(err => console.error(err));
                        });
                    }
                })
                .catch(err => {
                    console.error('[reviews] failed', err);
                    reviewsContainer.innerHTML = '<p>Ошибка при загрузке отзывов (см. консоль).</p>';
                });

            function escapeHtml(str) {
                return String(str ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }
        });


        document.addEventListener('DOMContentLoaded', () => {
            const reviewStars = document.getElementById('review-stars');
            const submitBtn = document.getElementById('submit-review');
            let selectedRating = 10; // по умолчанию

            if (reviewStars) {
                // Генерируем звёздочки
                for (let i = 1; i <= 10; i++) {
                    const star = document.createElement('span');
                    star.textContent = '★';
                    star.dataset.value = i;
                    star.addEventListener('mouseover', () => highlightStars(i));
                    star.addEventListener('mouseout', () => highlightStars(selectedRating));
                    star.addEventListener('click', () => {
                        selectedRating = i;
                        highlightStars(selectedRating);
                    });
                    reviewStars.appendChild(star);
                }
                highlightStars(selectedRating);

                function highlightStars(n) {
                    Array.from(reviewStars.children).forEach((s, idx) => {
                        s.classList.toggle('highlighted', idx < n);
                    });
                }
            }

            if (submitBtn) {
                submitBtn.addEventListener('click', () => {
                    const text = document.getElementById('review-text').value.trim();
                    if (!text) {
                        alert('Введите текст отзыва!');
                        return;
                    }

                    const reviewIdEl = document.getElementById('review-id');
                    const isEdit = reviewIdEl && reviewIdEl.value;

                    const url = isEdit ? '/swad/controllers/update_review.php' : '/swad/controllers/submit_review.php';
                    const body = isEdit ?
                        `review_id=${encodeURIComponent(reviewIdEl.value)}&rating=${selectedRating}&text=${encodeURIComponent(text)}` :
                        `game_id=<?= $game_id ?>&rating=${selectedRating}&text=${encodeURIComponent(text)}`;

                    fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Ошибка: ' + (data.error || data.message || 'unknown'));
                            }
                        })
                        .catch(err => console.error(err));
                });

            }
        });
    </script>
    <script>
        async function pay(productId) {
            const res = await fetch('/finv2/create_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId
                })
            });

            const data = await res.json();

            if (!data.PaymentURL) {
                alert('Ошибка оплаты');
                return;
            }

            // Вариант 1 — редирект
            window.location.href = data.PaymentURL;

            // Вариант 2 — iframe через integration.js
            /*
            PaymentIntegration.open({
                paymentId: data.PaymentId
            });
            */
        }
    </script>
</body>

</html>