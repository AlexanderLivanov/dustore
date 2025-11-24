<?php

// 1.
// Оплата заданной суммы с выбором валюты на сайте мерчанта
// Payment of the set sum with a choice of currency on merchant site 

// регистрационная информация (логин, пароль #1)
// registration info (login, password #1)
$mrh_login = "cplrus";
$mrh_pass1 = "UF4oF54w9FNILTdhGzv9";

// номер заказа
// number of order
$inv_id = 123;

// описание заказа
// order description
$inv_desc = "ROBOKASSA Advanced User Guide";

// сумма заказа
// sum of order
$out_summ = "100";

// предлагаемая валюта платежа
// default payment e-currency
$in_curr = "";

// язык
// language
$culture = "ru";
$IsTest = 1;

// кодировка
// encoding
$encoding = "utf-8";

$shp_item = 'igra';

// формирование подписи
// generate signature
$crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1:Shp_item=$shp_item");

print($crc);
echo "<br>" . $mrh_login . ":" . $out_summ . ":" . $inv_id . ":" . $mrh_pass1 . ":Shp_item=" . $shp_item . "<br>";


// HTML-страница с кассой
// ROBOKASSA HTML-page
print "<html><script language=JavaScript ".
      "src='https://auth.robokassa.ru/Merchant/PaymentForm/FormFLS.js?".
      "MerchantLogin=$mrh_login&OutSum=$out_summ&InvId=$inv_id&IncCurrLabel=$in_curr".
      "&Description=$inv_desc&SignatureValue=$crc".
      "&Culture=$culture&Encoding=$encoding&Shp_item=$shp_item&IsTest=$IsTest'></script></html>";
?>