<?

// 1.
// Оплата заданной суммы с выбором валюты на сайте мерчанта
// Payment of the set sum with a choice of currency on merchant site 

// регистрационная информация (логин, пароль #1)
// registration info (login, password #1)
$mrh_login = "cplrus";
$mrh_pass1 = "Pi9g8Vi68FHED2dHANbO";

// номер заказа
// number of order
$inv_id = 1;

// описание заказа
// order description
$inv_desc = "ROBOKASSA Advanced User Guide";

// сумма заказа
// sum of order
$out_summ = "13.37";

// тип товара
// code of goods
$shp_item = 1;

// предлагаемая валюта платежа
// default payment e-currency
$in_curr = "";

// язык
// language
$culture = "ru";

// кодировка
// encoding
$encoding = "utf-8";

// формирование подписи
// generate signature
$crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1:Shp_item=$shp_item");

// HTML-страница с кассой
// ROBOKASSA HTML-page
echo("<html>
<script src='https://auth.robokassa.ru/Merchant/PaymentForm/FormFLS.js?MerchantLogin=$mrh_login&OutSum=$out_summ&InvId=$inv_id&IncCurrLabel=$in_curr&Description=$inv_desc&SignatureValue=$crc&Shp_item=$shp_item&Culture=$culture&Encoding=$encoding'>
</script>
</html>");