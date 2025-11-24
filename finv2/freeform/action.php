<?php 

if (!empty($_POST["out_sum"])) {

    $out_sum = trim(htmlspecialchars(strip_tags($_POST["out_sum"])));

// your registration data
    $mrh_login = "cplrus"; 
    $mrh_pass1 = "UF4oF54w9FNILTdhGzv9";

// order number. "" for random value
    $inv_id = "";

// urlencoded receipt
    $receipt = "%7B%22items%22:%5B%7B%22name%22:%22name%22,%22quantity%22:1,".
    "%22sum%22:11,%22tax%22:%22none%22%7D%5D%7D";

// double urlencode for headers
    $receipt_urlencode = urlencode($receipt);

// description of the order, if you need
    $inv_desc = "description";

// build own CRC
    $crc = md5("$mrh_login:$out_sum:$inv_id:$receipt:$mrh_pass1");

// payment form
    Header
("Location: https://auth.robokassa.ru/Merchant/Index.aspx?MrchLogin=$mrh_login&".
    "OutSum=$out_sum&InvId=$inv_id&Receipt=$receipt_urlencode&IsTest=1&Desc=$inv_desc&".
    "SignatureValue=$crc");
}