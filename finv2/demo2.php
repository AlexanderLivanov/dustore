<?php

// 2.
// Оплата заданной суммы с выбором валюты на сайте ROBOKASSA
// Payment of the set sum with a choice of currency on site ROBOKASSA

// регистрационная информация (логин, пароль #1)
// registration info (login, password #1)
$mrh_login = "cplrus";
$mrh_pass1 = "UF4oF54w9FNILTdhGzv9";

// номер заказа
// number of order
$inv_id = 0;
$IsTest = 1;

// описание заказа
// order description
$inv_desc = "ROBOKASSA Advanced User Guide";

// сумма заказа
// sum of order
$out_summ = "100";

// тип товара
// code of goods
$shp_item = "2";

// предлагаемая валюта платежа
// default payment e-currency
$in_curr = "";

// язык
// language
$culture = "ru";

// формирование подписи
// generate signature
$crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1:Shp_item=$shp_item");

// форма оплаты товара
// payment form
print "<html>".
      "<form action='https://auth.robokassa.ru/Merchant/Index.aspx' method=POST>".
      "<input type=hidden name=MerchantLogin value=$mrh_login>".
      "<input type=hidden name=OutSum value=$out_summ>".
      "<input type=hidden name=InvId value=$inv_id>".
      "<input type=hidden name=Description value='$inv_desc'>".
      "<input type=hidden name=SignatureValue value=$crc>".
      "<input type=hidden name=Shp_item value='$shp_item'>".
      "<input type=hidden name=IncCurrLabel value=$in_curr>".
      "<input type=hidden name=Culture value=$culture>".
      "<input type=hidden name=IsTest value=$IsTest>" .
      "<input type=submit value='Pay'>".
      "</form></html>";
?>