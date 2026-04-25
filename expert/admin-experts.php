<?php
session_start();
require_once __DIR__ . '/../swad/config.php';

$db = new Database();
$pdo = $db->connect();

// Получаем все заявки на экспертов
$stmt = $pdo->query("
    SELECT e.id AS expert_id, e.status, e.rating, e.votes_count,
           u.id AS user_id, u.username, u.first_name, u.last_name, u.email
    FROM experts e
    JOIN users u ON e.user_id = u.id
    ORDER BY e.status ASC, e.created_at DESC
");
$experts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Админка: Эксперты</title>
    <style>
        body {
            font-family: Arial;
            background: #f0f0f0;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .approve {
            background: #4CAF50;
            color: white;
        }

        .reject {
            background: #f44336;
            color: white;
        }

        .approve:hover {
            background: #45a049;
        }

        .reject:hover {
            background: #e53935;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Заявки на экспертов</h1>

        <table>
            <tr>
                <th>Пользователь</th>
                <th>Email</th>
                <th>Статус</th>
                <th>Рейтинг</th>
                <th>Проверенные игры</th>
                <th>Действия</th>
            </tr>

            <?php foreach ($experts as $exp): ?>
                <tr>
                    <td><?= htmlspecialchars($exp['username'] ?: $exp['first_name'] . ' ' . $exp['last_name']) ?></td>
                    <td><?= htmlspecialchars($exp['email']) ?></td>
                    <td><?= htmlspecialchars($exp['status']) ?></td>
                    <td><?= round($exp['rating'], 2) ?></td>
                    <td><?= $exp['votes_count'] ?></td>
                    <td>
                        <?php if ($exp['status'] == 'new'): ?>
                            <form method="post" action="update-expert.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $exp['expert_id'] ?>">
                                <button type="submit" name="action" value="approve" class="approve">Одобрить</button>
                                <button type="submit" name="action" value="reject" class="reject">Отклонить</button>
                            </form>
                        <?php else: ?>
                            <em>Действия недоступны</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

    </div>
</body>

</html>