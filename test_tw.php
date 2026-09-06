<?php
$hash = "01a070fb7f8e7baa94d52531892d4f";
$mobile = "0812345678"; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://gift.truemoney.com/campaign/vouchers/".$hash."/redeem");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json",
    "Origin: https://gift.truemoney.com",
    "Referer: https://gift.truemoney.com/campaign/?v=" . $hash
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["mobile" => $mobile, "voucher_hash" => $hash]));
$res = curl_exec($ch);
curl_close($ch);
echo "Response: " . $res;

