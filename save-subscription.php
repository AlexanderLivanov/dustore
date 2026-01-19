<?php
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) exit("No subscription");

// Читаем все подписки
$allSubs = [];
if (file_exists("subscriptions.json")) {
    $allSubs = json_decode(file_get_contents("subscriptions.json"), true);
}

// Сохраняем новую, если её ещё нет
$exists = false;
foreach ($allSubs as $sub) {
    if ($sub['endpoint'] === $data['endpoint']) {
        $exists = true;
        break;
    }
}
if (!$exists) $allSubs[] = $data;

// Сохраняем обратно
file_put_contents("subscriptions.json", json_encode($allSubs, JSON_PRETTY_PRINT));

echo "Saved";
