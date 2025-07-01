<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');

$curr_user = new User();
$db = new Database();
$conn = $db->connect();

if ($curr_user->checkAuth() > 0) {
    echo "<script>window.location.replace('/login');</script>";
    exit;
}

$user_data = $_SESSION['USERDATA'];
$userId = $user_data['id'];
$error = null; // Инициализация переменных
$success = null;

if (empty($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
$form_token = $_SESSION['form_token'];

$studios = $db->Select("SELECT id FROM studios WHERE owner_id = ?", [$userId]);
if (count($studios) >= 1) {
    $error = "Вы уже зарегистрировали студию. У одного пользователя может быть только одна студия.";
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

        if ($specialization === 'soft') {
            $specialization = 'software';
        }

        if (empty($name) || empty($description) || empty($vkLink) || empty($tgLink) || empty($email)) {
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
                    $data = [
                        'status' => 'pending',
                        'ban_reason' => '',
                        'name' => $name,
                        'owner_id' => $userId,
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
                        'pre_alpha_program' => $preAlpha
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
                        <h4 style="text-decoration: underline;">Внимание! Для прохождения модерации необходимо подтвердить права на владение студией. Для этого в ВК сообществе и Telegram-канале вашей студии создайте пост со следующим содержанием:</h4>
                        <br>
                        <div class="al" style="border-left: 2px solid #c32178; padding: 5px;">
                            <i>Студия "<Ваша студия>" теперь есть на новой игровой платформе Dustore.Ru и скоро добавит свои проекты!</i>
                        </div>
                        <br>
                        <div class="form-group">
                            <label for="vk_link" class="required">Ссылка на ВК группу</label>
                            <input type="url" id="vk_link" name="vk_link" required
                                placeholder="https://vk.com/ваша_группа"
                                maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="tg_link" class="required">Ссылка на Telegram канал</label>
                            <input type="url" id="tg_link" name="tg_link" required
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
                            <label for="team_size">Размер команды</label>
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
                        <p class="form-note">Участники ППО получают бесплатную регистрацию студтии, первой игры, уникальные бейджи и приоритетную техническую поддержку</p>
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" value="1" required>
                            <label for="terms" class="required">Согласен с <a href="/oferta" style="color: #14041d;">условиями использования</a></label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><span class="icon">💳</span> Платежная информация</h3>

                        <div class="form-group">
                            <label for="payment_type">Тип оплаты</label>
                            <select id="payment_type" name="payment_type" disabled>
                                <option value="">Выберите тип оплаты</option>
                                <option value="bank">Счёт в банке</option>
                            </select>
                        </div>

                        <!-- <div class="form-group">
                            <label for="bank_name">Название банка</label>
                            <input type="text" id="bank_name" name="bank_name"
                                placeholder="Введите название банка">
                        </div>

                        <div class="form-group">
                            <label for="account_number">Номер счета</label>
                            <input type="text" id="account_number" name="account_number"
                                placeholder="Введите номер счета">
                        </div> -->

                        <div class="form-group">
                            <label for="tax_id">ИНН</label>
                            <input type="text" id="tax_id" name="tax_id"
                                placeholder="Введите налоговый номер" disabled>
                        </div>

                        <div class="form-group">
                            <label for="payment_agent">Платёжный агрегатор</label>
                            <select id="payment_agent" name="payment_agent" disabled>
                                <option value="">Выберите свой сервис</option>
                                <option value="...">...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="payment_token">Токен для оплаты</label>
                            <input type="text" id="payment_token" name="payment_token"
                                placeholder="Токен для оплаты можно получить у платёжного агрегатора" disabled>
                        </div>

                        <!-- <div class="form-group">
                            <label for="billing_address">Платежный адрес</label>
                            <textarea id="billing_address" name="billing_address"
                                placeholder="Введите полный платежный адрес"></textarea>
                        </div> -->

                        <h4 style="text-decoration: underline;">Возможность создания платёжного профиля будет доступна после завершения Программы Предварительной Оценки</h4>
                    </div>
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

        // Подсветка полей при фокусе
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