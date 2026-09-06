<?php
$start = microtime(true);
$dsn = "pgsql:host=ep-odd-scene-b3mcwphv-pooler.c-4.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$pdo = new PDO($dsn, "neondb_owner", "npg_Fxload8cknX7", [PDO::ATTR_PERSISTENT => true]);
$pdo->query("SELECT 1");
$end = microtime(true);
echo "DB Connection + Query took: " . number_format($end - $start, 4) . " seconds\n";

