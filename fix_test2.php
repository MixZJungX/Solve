<?php
$c = file_get_contents("api.php");
$c = str_replace("\$res[] = \"HTTP \$httpCode: \$raw\";", "\$res[] = \"HTTP \$httpCode: \$raw | ERROR: \" . curl_error(\$ch);", $c);
file_put_contents("api.php", $c);

