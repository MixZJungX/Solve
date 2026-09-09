<?php
$c = file_get_contents("public/admin.js");
$oldClear = "    document.getElementById('settingAdminPass').value = '';";
$newClear = "    document.getElementById('settingAdminPass').value = '';\n    if (document.getElementById('settingZsKey')) document.getElementById('settingZsKey').value = '';";
$c = str_replace($oldClear, $newClear, $c);
file_put_contents("public/admin.js", $c);

