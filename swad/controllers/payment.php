<?php
// $md5_hash = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");

// $crc = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");
// print "<html><script language=JavaScript " .
//     "src='https://auth.robokassa.ru/Merchant/PaymentForm/FormMS.js?" .
//     "MerchantLogin=$mrh_login&OutSum=$out_summ&InvoiceID=$inv_id" .

//     "&Description=$inv_desc&SignatureValue=$crc&IsTest=$IsTest'></script></html>";

function renderPaymentButton($_mrh_login, $_mrh_pass1, $_inv_id, $_inv_desc, $_items, $_Email, $_out_sum, $_IsTest, $_shp_item, $_BTNTEXT){
    $mrh_login = $_mrh_login;
    $mrh_pass1 = $_mrh_pass1;
    $inv_id = $_inv_id;
    $inv_desc = $_inv_desc;
    $items = [];
    foreach ($_items as $item) {
        $items[] = [
            'name' => $item[0],
            'quantity' => 1,
            'sum' => $item[1],
            'tax' => 'none'
        ];
        $_out_sum += $item[1];
    }
    $receipt = urlencode(json_encode(['items' => $items]));
    $Email = $_Email;
    $ExpirationDate = (new DateTime())->modify('+24 hours')->format('Y-m-d\TH:i');;
    $incurrlabel = "BANKOCEAN2R";
    $culture = "ru";
    $encoding = "utf-8";
    $out_sum = $_out_sum;
    $IsTest = $_IsTest;
    $shp_item = $_shp_item;

    $signature_value = md5("$mrh_login:$out_sum:$inv_id:$receipt:$mrh_pass1:Shp_item=$shp_item");

    print
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
        "<input type=submit value='$_BTNTEXT' class='btn-buy'>" .
        "</form>";
}
?>
