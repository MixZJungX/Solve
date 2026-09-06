<?php
$content = file_get_contents("db_old.php");

// 1. Replace PDO connection
$content = preg_replace(
    "/self::\\\$pdo = new PDO\('sqlite:' \. \\\$dbPath\);/",
    "\\\$dsn = 'pgsql:host=ep-odd-scene-b3mcwphv-pooler.c-4.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require';\n            self::\\\$pdo = new PDO(\\\$dsn, 'neondb_owner', 'npg_Fxload8cknX7');",
    $content
);

// 2. Replace AUTOINCREMENT with SERIAL
$content = str_replace("INTEGER PRIMARY KEY AUTOINCREMENT", "SERIAL PRIMARY KEY", $content);

// 3. Replace DATETIME DEFAULT CURRENT_TIMESTAMP with TIMESTAMP DEFAULT CURRENT_TIMESTAMP
$content = str_replace("DATETIME DEFAULT CURRENT_TIMESTAMP", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $content);

// 4. Remove COLLATE NOCASE from schemas
$content = str_replace("COLLATE NOCASE", "", $content);

// 5. Replace sqlite-specific queries
$content = str_replace(
    "INSERT OR IGNORE INTO settings",
    "INSERT INTO settings (key, value) VALUES",
    $content
);
$content = str_replace(
    "ON CONFLICT(key) DO UPDATE",
    "ON CONFLICT (key) DO UPDATE",
    $content
);

$content = str_replace(
    "INSERT OR IGNORE INTO members",
    "INSERT INTO members",
    $content
);

// Handle specific query cases for case-insensitivity
$content = str_replace("WHERE username = ? COLLATE NOCASE", "WHERE LOWER(username) = LOWER(?)", $content);
$content = str_replace("WHERE email = ? COLLATE NOCASE", "WHERE LOWER(email) = LOWER(?)", $content);

file_put_contents("db.php", $content);

