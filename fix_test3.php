<?php
$c = file_get_contents("api.php");
$c = preg_replace("/case 'test_zp':.*?break;/s", "", $c);
file_put_contents("api.php", $c);

