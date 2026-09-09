<?php
$c = file_get_contents("api.php");
$c = preg_replace('/CURLOPT_URL => "https:\/\/zeropoint\.to\/api\/zerosolver-api\/submit",\s+CURLOPT_RETURNTRANSFER => true,/s', "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\",\n                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,", $c);

$c = preg_replace('/CURLOPT_URL => "https:\/\/zeropoint\.to\/api\/zerosolver-api\/status\/\{\$id\}",\s+CURLOPT_RETURNTRANSFER => true,/s', "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",\n                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,", $c);
file_put_contents("api.php", $c);

