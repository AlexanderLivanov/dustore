<?php
// Пароль #2 из личного кабинета Robokassa
$mrh_pass1 = "UF4oF54w9FNILTdhGzv9";

// Чтение параметров от Robokassa
$out_summ = $_REQUEST["OutSum"];
$inv_id = $_REQUEST["InvId"];
$crc = strtoupper($_REQUEST["SignatureValue"]);

// Локальный расчет подписи для проверки
$my_crc = strtoupper(md5("$out_summ:$inv_id:$mrh_pass1"));

echo "Полученная подпись: " . $crc . "<br>";
echo "Вычисленная подпись: " . $my_crc . "<br>";

// Проверка корректности подписи
if ($my_crc != $crc) {
  echo "Ошибка: подпись не совпадает!";
  exit();
}

// Если подписи совпали - операция успешна
echo "OK$inv_id\n";
echo "Платеж прошел успешно!";
