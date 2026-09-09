<?php
$c = file_get_contents("api.php");

// 1. get_settings
$oldGet = "                    'zp_masked_key' => \$zpMasked,";
$newGet = "                    'zp_masked_key' => \$zpMasked,\n                    'has_zs_key' => !empty(DB::getSetting('zerosolver_api_key')),";
$c = str_replace($oldGet, $newGet, $c);

// 2. save_settings
$oldSave = "            if (isset(\$input['inw_api_key'])) {";
$newSave = "            if (isset(\$input['zerosolver_api_key'])) {\n                DB::setSetting('zerosolver_api_key', trim(\$input['zerosolver_api_key']));\n            }\n            if (isset(\$input['inw_api_key'])) {";
$c = str_replace($oldSave, $newSave, $c);

// 3. customer_submit
$c = str_replace(
    "\$zpKey = DB::getSetting('zp_api_key', '');\n                if (empty(\$zpKey)) {\n                    jsonResponse(['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroPoint API Key'], 503);\n                }",
    "\$zpKey = DB::getSetting('zerosolver_api_key', '');\n                if (empty(\$zpKey)) {\n                    jsonResponse(['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroSolver API Key'], 503);\n                }",
    $c
);

// 4. job_status (x2 for customer_job_status and get_job_status)
$c = str_replace(
    "\$localJobCheck['service'] === 'captcha_zp') {\n                \$zpKey = DB::getSetting('zp_api_key', '');",
    "\$localJobCheck['service'] === 'captcha_zp') {\n                \$zpKey = DB::getSetting('zerosolver_api_key', '');",
    $c
);

file_put_contents("api.php", $c);

