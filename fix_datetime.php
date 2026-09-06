<?php
$content = file_get_contents("db.php");
$content = str_replace("datetime('now')", "CURRENT_TIMESTAMP", $content);
$content = str_replace("DATETIME DEFAULT NULL", "TIMESTAMP DEFAULT NULL", $content);
file_put_contents("db.php", $content);

