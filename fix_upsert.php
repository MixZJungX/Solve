<?php
$content = file_get_contents("db.php");

// Fix upsertAccount to lowercase username
$content = preg_replace(
    "/public static function upsertAccount\(string \\\$username, string \\\$cookie, string \\\$password = '', string \\\$note = ''\): bool {/",
    "public static function upsertAccount(string \$username, string \$cookie, string \$password = '', string \$note = ''): bool {\n        \$username = strtolower(\$username);",
    $content
);

// Fix updateAccountUsage to lowercase username
$content = preg_replace(
    "/public static function updateAccountUsage\(string \\\$username, string \\\$jobId, string \\\$status = ''\): void {/",
    "public static function updateAccountUsage(string \$username, string \$jobId, string \$status = ''): void {\n        \$username = strtolower(\$username);",
    $content
);

// Fix updateAccountStatus to lowercase username
$content = preg_replace(
    "/public static function updateAccountStatus\(string \\\$username, string \\\$status\): void {/",
    "public static function updateAccountStatus(string \$username, string \$status): void {\n        \$username = strtolower(\$username);",
    $content
);

// Fix getAccount to lowercase username
$content = preg_replace(
    "/public static function getAccount\(string \\\$username\): \?array {/",
    "public static function getAccount(string \$username): ?array {\n        \$username = strtolower(\$username);",
    $content
);

// Fix getMemberByEmail to lowercase email
$content = preg_replace(
    "/public static function getMemberByEmail\(string \\\$email\): \?array {/",
    "public static function getMemberByEmail(string \$email): ?array {\n        \$email = strtolower(\$email);",
    $content
);

// Fix createMember to lowercase email
$content = preg_replace(
    "/public static function createMember\(string \\\$email, string \\\$passwordHash\): bool {/",
    "public static function createMember(string \$email, string \$passwordHash): bool {\n        \$email = strtolower(\$email);",
    $content
);

file_put_contents("db.php", $content);

