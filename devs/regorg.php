<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');
require_once('../swad/controllers/tg_bot.php');

$curr_user = new User();
$db = new Database();
$conn = $db->connect();

if ($curr_user->checkAuth() > 0) {
    echo "<script>window.location.replace('/login');</script>";
    exit;
}

$user_data = $_SESSION['USERDATA'];
$userId = $user_data['id'];
$error = null;
$success = null;

function generateTicker($conn) {
    $length = rand(4, 5);
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    do {
        $ticker = '';
        for ($i = 0; $i < $length; $i++) {
            $ticker .= $characters[rand(0, strlen($characters) - 1)];
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM studios WHERE ticker = ?");
        $stmt->execute([$ticker]);
        $exists = $stmt->fetchColumn();

    } while ($exists > 0);

    return $ticker;
}


if (empty($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
$form_token = $_SESSION['form_token'];

$studios = $db->Select("SELECT id FROM studios WHERE owner_id = ?", [$userId]);
if (count($studios) >= 1) {
    $error = "Вы уже зарегистрировали студию. У одного пользователя может быть только одна студия.";
    echo ("<script>window.location.href = 'select';</script>");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    if (!isset($_POST['token']) || $_POST['token'] !== $form_token) {
        $error = "Ошибка безопасности. Пожалуйста, отправьте форму снова.";
    } elseif (count($db->Select("SELECT id FROM studios WHERE owner_id = ?", [$userId])) >= 1) {
        $error = "Вы уже зарегистрировали студию. У одного пользователя может быть только одна студия.";
    } else {
        $name = $_POST['org_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $website = $_POST['website'] ?? null;
        $country = $_POST['country'] ?? null;
        $city = $_POST['city'] ?? null;
        $vkLink = $_POST['vk_link'] ?? '';
        $tgLink = $_POST['tg_link'] ?? '';
        $email = $_POST['email'] ?? '';
        $foundationDate = $_POST['foundation_year'] ?? null;
        $teamSize = $_POST['team_size'] ?? null;
        $specialization = $_POST['specialization'] ?? null;
        $preAlpha = isset($_POST['pre_alpha']) ? 1 : 0;
        $bank_name = $_POST['bank_name'] ?? '';
        $BIC = $_POST['BIC'] ?? '';
        $account_number = $_POST['account_number'] ?? '';
        $INN = $_POST['tax_id'] ?? '';

        if ($specialization === 'soft') {
            $specialization = 'software';
        }

        if (empty($name) || empty($description) || empty($email)) {
            $error = "Пожалуйста, заполните все обязательные поля";
        } else {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Некорректный формат email";
            }
            $urls = [$vkLink, $tgLink, $website];
            foreach ($urls as $url) {
                if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
                    $error = "Некорректный URL: $url";
                    break;
                }
            }

            if (empty($error)) {
                try {
                    $ticker = generateTicker($conn);
                    $data = [
                        'status' => 'pending',
                        'ban_reason' => '',
                        'name' => $name,
                        'owner_id' => $userId,
                        'ticker' => $ticker,
                        'description' => $description,
                        'vk_link' => $vkLink,
                        'tg_link' => $tgLink,
                        'website' => $website,
                        'country' => $country,
                        'city' => $city,
                        'contact_email' => $email,
                        'foundation_date' => $foundationDate,
                        'team_size' => $teamSize,
                        'specialization' => $specialization,
                        'pre_alpha_program' => $preAlpha,
                        'bank_name' => $bank_name,
                        'BIC' => $BIC,
                        'acc_num' => $account_number, 
                        'INN' => $INN 
                    ];

                    $columns = implode(', ', array_keys($data));
                    $placeholders = implode(', ', array_fill(0, count($data), '?'));
                    $sql = "INSERT INTO studios ($columns) VALUES ($placeholders)";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute(array_values($data));
                    $studioId = $conn->lastInsertId();

                    $staffData = [
                        'telegram_id' => $user_data['telegram_id'] ?? null,
                        'org_id' => $studioId,
                        'created' => date('Y-m-d H:i:s'),
                        'role' => 'Владелец'
                    ];

                    $staffColumns = implode(', ', array_keys($staffData));
                    $staffPlaceholders = implode(', ', array_fill(0, count($staffData), '?'));
                    $staffSql = "INSERT INTO staff ($staffColumns) VALUES ($staffPlaceholders)";
                    $db->Insert($staffSql, array_values($staffData));

                    unset($_SESSION['form_token']);
                    send_group_message(-1002916906978, "Получена новая заяка на регистрацию студии!\n
Название: <i>" . $data['name'] . "</i>
Описание: <i>" . $data['description'] . "</i>
Почта для связи: <i>" . $data['contact_email'] . "</i>
ВК группа: <i>" . $data['vk_link'] . "</i>
Telegram: <i>" . $data['tg_link'] . "</i>", true, "https://dustore.ru/devs/recentorgs");
                    send_private_message(
                        $user_data['telegram_id'],
                        "Заявка на регистрацию вашей студии была отправлена на модерацию. А теперь - будем знакомиться! 😊\n
Меня зовут Дасти 😎 - я бот-ассистент на Платформе Dustore.Ru.
Я буду присылать вам важные уведомления. А ещё вы можете добавить меня в чат вашей студии, куда я буду присылать еженедельную статистику, новости и уведомления. 
О том, как это сделать, <a href='https://github.com/AlexanderLivanov/dustore-docs/wiki/Добавление-бота-в-чат-вашей-студии'>читайте здесь.</a>\n
Спасибо, что пользуетесь Dustore ❤

[ <a href='https://dustore.ru'>Сайт Платформы</a> ] [ <a href='https://t.me/dustore_official'>Новостной канал Платформы</a> ] [ <a href='https://vk.com/crazyprojectslab'>Crazy Projects Lab</a> ] [ <a href='https://vk.com/dgscorp'>Dust Studio</a> ]"
                    );
                    echo "<script>window.location.replace('/devs/select');</script>";
                    exit;
                } catch (Exception $e) {
                    $error = "Ошибка при создании студии: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore.Devs | Регистрация студии</title>
    <link rel="stylesheet" href="/swad/css/regorg.css">
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="page-header">
            <h1>Регистрация студии</h1>
            <p>Создайте свою студию на Dustore.Devs и получите доступ к экосистеме для инди-разработчиков</p>
        </div>

        <div class="registration-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($form_token) ?>">
                <div class="form-grid">
                    <div class="form-section">
                        <h3><span class="icon">🏢</span> Основная информация</h3>

                        <div class="form-group">
                            <label for="org_name" class="required">Название студии</label>
                            <input type="text" id="org_name" name="org_name" required
                                placeholder="Введите название (только буквы и цифры)"
                                maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="description" class="required">Описание студии</label>
                            <textarea id="description" name="description" required
                                placeholder="Расскажите о вашей студии, её истории и проектах"
                                maxlength="1500"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="website">Веб-сайт (если есть)</label>
                            <input type="url" id="website" name="website"
                                placeholder="https://ваша-студия.com">
                        </div>

                        <div class="form-group">
                            <label for="country">Страна</label>
                            <select id="country" name="country">
                                <option value="">Выберите страну</option>
                                <option value="ru">Россия</option>
                                <option value="by">Беларусь</option>
                                <option value="kz">Казахстан</option>
                                <option value="other">Другая</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="city">Город</label>
                            <input type="text" id="city" name="city"
                                placeholder="Введите ваш город">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><span class="icon">🔗</span> Ссылки и контакты</h3>
                        <div class="form-group">
                            <label for="vk_link">Ссылка на ВК группу</label>
                            <input type="url" id="vk_link" name="vk_link"
                                placeholder="https://vk.com/ваша_группа"
                                maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="tg_link">Ссылка на Telegram канал</label>
                            <input type="url" id="tg_link" name="tg_link"
                                placeholder="https://t.me/ваш_канал"
                                maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">Контактный email</label>
                            <h6>Этот почтовый адрес будет опубликован на платформе, чтобы с вами могли связаться игроки и администрация платформы</h6>
                            <br>
                            <input type="email" id="email" name="email"
                                placeholder="contact@ваша-студия.com" required>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><span class="icon">⚙️</span> Настройки студии</h3>

                        <div class="form-group">
                            <label for="foundation_year">Дата основания</label>
                            <h6>
                                Например: 15.02.2025
                            </h6>
                            <br>
                            <input type="date" id="foundation_year" name="foundation_year">
                        </div>

                        <div class="form-group">
                            <label for="team_size" class="required">Размер команды</label>
                            <select id="team_size" name="team_size">
                                <option value="">Выберите размер команды</option>
                                <option value="1">1 человек (инди-разработчик)</option>
                                <option value="2-5">2-5 человек</option>
                                <option value="6-10">6-10 человек</option>
                                <option value="11-20">11-20 человек</option>
                                <option value="20+">Более 20 человек</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="specialization">Специализация</label>
                            <select id="specialization" name="specialization">
                                <option value="">Выберите специализацию</option>
                                <option value="mobile">Мобильные игры</option>
                                <option value="pc">PC игры</option>
                                <option value="console">Консольные игры</option>
                                <option value="vr">VR/AR игры</option>
                                <option value="software">Разработка приложений</option>
                                <option value="all">Разные платформы</option>
                            </select>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="pre_alpha" name="pre_alpha" value="1">
                            <label for="pre_alpha">Хочу участвовать в Программе Предварительной Оценки (ППО)</label>
                        </div>
                        <p class="form-note">Участники ППО получают ранние анонсы, уникальные бейджи и приоритетную техническую поддержку</p>
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" value="1" required>
                            <label for="terms" class="required">Согласен с <a href="/oferta.txt" style="color: #14041d;">условиями использования</a></label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><span class="icon">💳</span> Платежная информация</h3>

                        <h4>На этот счёт будут приходить выплаты и гранты</h4>

                        <br>
                        <br>

                        <div class="form-group">
                            <label for="bank_name">Название банка</label>
                            <input type="text" id="bank_name" name="bank_name" minlength="5"
                                placeholder="Введите название банка">
                        </div>

                        <div class="form-group">
                            <label for="BIC">БИК</label>
                            <input type="text" id="BIC" name="BIC" maxlength="9" minlength="9"
                                placeholder="Введите БИК банка">
                        </div>

                        <div class="form-group">
                            <label for="account_number">Номер счета</label>
                            <input type="text" id="account_number" name="account_number" minlength="20" maxlength="20"
                                placeholder="Введите номер счета">
                        </div>

                        <div class="form-group">
                            <label for="tax_id">ИНН</label>
                            <input type="text" id="tax_id" name="tax_id" maxlength="12" minlength="10"
                                placeholder="Введите налоговый номер">
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 50px">
                    <h5>Результаты модераций, уведомления, статистика и много чего ещё в нашем боте:</h5>
                    <a href="https://t.me/dustore_auth_bot" style="color: #14041d;" target="_blank">https://t.me/dustore_auth_bot</a>
                    <br>
                    <br>
                    <img style=" border-radius: 15px;" src="/swad/static/img/dusty_tg_qr.png" alt="" width="25%">
                </div>

                <div class="form-actions">
                    <button type="submit" class="form-submit" style="background-color: green;">
                        <span>🚀 Создать студию</span>
                    </button>
                </div>
                <div class="form-actions" onclick="window.location.replace('/me');">
                    <button type="button" class="form-submit" style="background-color: red;">
                        <span>❌ Я передумал, верните меня обратно</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Валидация формы
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;

            // Проверка обязательных полей
            const requiredFields = document.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc3545';
                } else {
                    field.style.borderColor = '';
                }
            });

            // Проверка согласия с условиями
            const terms = document.getElementById('terms');
            if (!terms.checked) {
                isValid = false;
                terms.parentElement.style.color = '#dc3545';
            } else {
                terms.parentElement.style.color = '';
            }

            if (!isValid) {
                e.preventDefault();
                alert('Пожалуйста, заполните все обязательные поля и примите условия использования');
            }
        });

        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.borderColor = '#c32178';
                this.style.backgroundColor = 'rgba(255, 255, 255, 0.12)';
            });

            input.addEventListener('blur', function() {
                this.style.borderColor = '';
                this.style.backgroundColor = '';
            });
        });
    </script>
</body>

</html>