<?php
// migrate.php - Run this ONCE locally to migrate SQLite data to Neon

$sqliteFile = __DIR__ . "/data/highspec.db";
if (!file_exists($sqliteFile)) {
    die("Error: Please put the downloaded highspec.db inside the data folder first.\n");
}

echo "Connecting to SQLite...\n";
$sqlite = new PDO("sqlite:" . $sqliteFile);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "Connecting to Neon PostgreSQL...\n";
$dsn = "pgsql:host=ep-crimson-credit-arkqmq69-pooler.c-4.us-west-2.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$user = "neondb_owner";
$pass = "npg_pTE1Uzgekdw3";

try {
    $pg = new PDO($dsn, $user, $pass);
    $pg->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Postgres Connection Failed: " . $e->getMessage() . "\n");
}

echo "Creating tables in Neon...\n";

// settings
$pg->exec("CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT
)");

// members
$pg->exec("CREATE TABLE IF NOT EXISTS members (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    credits INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// topup_log
$pg->exec("CREATE TABLE IF NOT EXISTS topup_log (
    id SERIAL PRIMARY KEY,
    member_id INTEGER NOT NULL,
    email VARCHAR(255) NOT NULL,
    link TEXT NOT NULL,
    amount_thb NUMERIC(10,2) NOT NULL,
    credits_added INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// promptpay_tx
$pg->exec("CREATE TABLE IF NOT EXISTS promptpay_tx (
    transaction_id VARCHAR(255) PRIMARY KEY,
    member_id INTEGER NOT NULL,
    amount NUMERIC(10,2) NOT NULL,
    credits_added INTEGER NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// accounts
$pg->exec("CREATE TABLE IF NOT EXISTS accounts (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password TEXT DEFAULT '',
    cookie TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'ACTIVE',
    note TEXT DEFAULT '',
    last_job_id VARCHAR(255) DEFAULT '',
    last_status VARCHAR(255) DEFAULT '',
    last_used_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// jobs
$pg->exec("CREATE TABLE IF NOT EXISTS jobs (
    id VARCHAR(255) PRIMARY KEY,
    service VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDING',
    priority INTEGER DEFAULT 0,
    note TEXT DEFAULT '',
    total_accounts INTEGER DEFAULT 0,
    total_amount INTEGER DEFAULT 0,
    success_amount INTEGER DEFAULT 0,
    fail_amount INTEGER DEFAULT 0,
    skip_amount INTEGER DEFAULT 0,
    refunded_amount INTEGER DEFAULT 0,
    accounts_json TEXT DEFAULT '[]',
    accounts_detail_json TEXT DEFAULT '[]',
    raw_response TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

echo "Migrating data...\n";

function migrateTable($tableName, $sqlite, $pg, $conflictColumn = null) {
    echo "  -> Migrating $tableName... ";
    try {
        $rows = $sqlite->query("SELECT * FROM $tableName")->fetchAll();
    } catch (Exception $e) {
        echo "Table does not exist in source.\n";
        return;
    }
    if (empty($rows)) {
        echo "0 rows.\n";
        return;
    }
    
    $cols = array_keys($rows[0]);
    $placeholders = implode(",", array_fill(0, count($cols), "?"));
    $colList = implode(",", $cols);
    
    $sql = "INSERT INTO $tableName ($colList) VALUES ($placeholders)";
    if ($conflictColumn) {
        $sql .= " ON CONFLICT ($conflictColumn) DO NOTHING";
    }
    
    $stmt = $pg->prepare($sql);
    $count = 0;
    foreach ($rows as $row) {
        try {
            $stmt->execute(array_values($row));
            $count++;
        } catch (Exception $e) {
            echo "\nError on $tableName: " . $e->getMessage() . "\n";
        }
    }
    echo "$count rows inserted.\n";
}

migrateTable("settings", $sqlite, $pg, "key");
migrateTable("members", $sqlite, $pg, "email");
migrateTable("topup_log", $sqlite, $pg);
migrateTable("promptpay_tx", $sqlite, $pg, "transaction_id");
migrateTable("accounts", $sqlite, $pg, "username");
migrateTable("jobs", $sqlite, $pg, "id");

// Fix PostgreSQL sequences for SERIAL columns after manual insert
$pg->exec("SELECT setval('members_id_seq', (SELECT COALESCE(MAX(id), 1) FROM members))");
$pg->exec("SELECT setval('topup_log_id_seq', (SELECT COALESCE(MAX(id), 1) FROM topup_log))");
$pg->exec("SELECT setval('accounts_id_seq', (SELECT COALESCE(MAX(id), 1) FROM accounts))");

echo "\nMigration complete! Data successfully pushed to Neon Postgres.\n";

