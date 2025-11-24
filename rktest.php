<?php
$mrh_login = "cplrus";
$mrh_pass1 = "Zc1ioToYhv78S7BaDsa9";
$inv_id = 123;
$inv_desc = "Тестовая покупка на Платформе Dustore.Ru";
$receipt = urlencode('{"items":[{"name":"Howl-Growl alpha-1.0","quantity":1,"sum":1,"tax":"none"}]}');
$Email = "sashalivanov2007@gmail.com";
$ExpirationDate = "2025-12-28T00:00";
$incurrlabel = "BANKOCEAN2R";
$culture = "ru";
$encoding = "utf-8";
$out_sum = "1337.00";
$IsTest = 1;
$shp_item = "My_param=julia";
$signature_value = md5("$mrh_login:$out_sum:$inv_id:$receipt:$mrh_pass1:Shp_item=$shp_item");

print
    "<html>" .
    "<form action='https://auth.robokassa.ru/Merchant/Index.aspx' method=POST>" .
    "<input type=hidden name=IsTest value=$IsTest>" .
    "<input type=hidden name=MerchantLogin value=$mrh_login>" .
    "<input type=hidden name=OutSum value=$out_sum>" .
    "<input type=hidden name=InvId value=$inv_id>" .
    "<input type=hidden name=Description value='$inv_desc'>" .
    "<input type=hidden name=SignatureValue value=$signature_value>" .
    "<input type=hidden name=Shp_item value='$shp_item'>" .
    "<input type=hidden name=IncCurrLabel value=$incurrlabel>" .
    "<input type=hidden name=Culture value=$culture>" .
    "<input type=hidden name=Email value=$Email>" .
    "<input type=hidden name=ExpirationDate value=$ExpirationDate>" .
    "<input type=hidden name=Receipt value=$receipt>" .
    "<input type=submit value='Оплатить'>" .
    "</form></html>";

// $md5_hash = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");

// $crc = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");
// print "<html><script language=JavaScript " .
//     "src='https://auth.robokassa.ru/Merchant/PaymentForm/FormMS.js?" .
//     "MerchantLogin=$mrh_login&OutSum=$out_summ&InvoiceID=$inv_id" .

//     "&Description=$inv_desc&SignatureValue=$crc&IsTest=$IsTest'></script></html>";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>

</body>

</html>