<?php
require_once "C:\\Users\\ninek\\.gemini\\antigravity\\scratch\\framyomyim-voucher\\TrueMoneyWalletVoucher.php";
use BossNz\TrueMoneyWallet\Voucher;
$tw = new Voucher();
$tw->setUser([
    Voucher::INPUT_PHONE_TYPE => "0812345678",
    Voucher::INPUT_VOUCHER_HASH => "01a070fb7f8e7baa94d52531892d4f"
]);
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://gift.truemoney.com/campaign/vouchers/01a070fb7f8e7baa94d52531892d4f/redeem",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode(array("mobile" => "0812345678","voucher_hash" => "01a070fb7f8e7baa94d52531892d4f")),
    CURLOPT_HTTPHEADER => array(
        "accept: application/json",
        "accept-encoding: gzip, deflate, br",
        "accept-language: en-US,en;q=0.9",
        "content-length: 59",
        "content-type: application/json",
        "origin: https://gift.truemoney.com",
        "referer: https://gift.truemoney.com/campaign/?v=01a070fb7f8e7baa94d52531892d4f",
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36 Edg/87.0.664.66",
    ),
));
$response = curl_exec($curl);
echo $response;

