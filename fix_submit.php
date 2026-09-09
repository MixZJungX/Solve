<?php
$c = file_get_contents("api.php");
$c = preg_replace("/\\\$zpKey = DB::getSetting\\('zp_api_key', ''\\);\\s*if \\(empty\\(\\\$zpKey\\)\\) {\\s*jsonResponse\\(\['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroPoint API Key'\], 503\\);\\s*}/u", "\$zpKey = DB::getSetting('zerosolver_api_key', '');\n                if (empty(\$zpKey)) {\n                    jsonResponse(['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroSolver API Key'], 503);\n                }", $c, 1);
file_put_contents("api.php", $c);

