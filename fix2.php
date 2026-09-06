<?php
$content = file_get_contents("db_old.php");

$content = preg_replace(
    "/self::\\\$pdo = new PDO\('sqlite:' \. \\\$dbPath\);/",
    "\\\$dsn = 'pgsql:host=ep-odd-scene-b3mcwphv-pooler.c-4.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require';\n            self::\\\$pdo = new PDO(\\\$dsn, 'neondb_owner', 'npg_Fxload8cknX7');",
    $content
);

$content = str_replace("INTEGER PRIMARY KEY AUTOINCREMENT", "SERIAL PRIMARY KEY", $content);
$content = str_replace("DATETIME DEFAULT CURRENT_TIMESTAMP", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $content);

$content = str_replace("WHERE username = ? COLLATE NOCASE", "WHERE LOWER(username) = LOWER(?)", $content);
$content = str_replace("WHERE email = ? COLLATE NOCASE", "WHERE LOWER(email) = LOWER(?)", $content);
$content = str_replace("WHERE username IN (\$placeholders) COLLATE NOCASE", "WHERE LOWER(username) IN (\$placeholders)", $content);

$content = str_replace("COLLATE NOCASE", "", $content);

$content = str_replace("INSERT OR IGNORE INTO settings (key, value) VALUES", "INSERT INTO settings (key, value) VALUES", $content);
$content = preg_replace("/\\\$pdo->exec\(\"INSERT INTO settings \(key, value\) VALUES \('(.*?)', '(.*?)'\)\"\);/", "\$pdo->exec(\"INSERT INTO settings (key, value) VALUES ('$1', '$2') ON CONFLICT (key) DO NOTHING\");", $content);

$content = str_replace("INSERT OR IGNORE INTO members (email, password_hash, status) VALUES (?, ?, ?)", "INSERT INTO members (email, password_hash, status) VALUES (?, ?, ?) ON CONFLICT (email) DO NOTHING", $content);

file_put_contents("db.php", $content);

