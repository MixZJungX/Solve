<?php
// db.php — Database initialization & helper class for SQLite

class DB {
    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            $dataDir = __DIR__ . '/data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }

            $dbPath = $dataDir . '/highspec.db';
            self::$pdo = new PDO('sqlite:' . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::initTables();
        }
        return self::$pdo;
    }

    private static function initTables(): void {
        $pdo = self::$pdo;

        // Settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");

        // Set defaults if not exist
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('queue_mode', 'normal')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password', 'admin1234')");

        // Accounts table
        $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL COLLATE NOCASE,
            password TEXT DEFAULT '',
            cookie TEXT NOT NULL,
            status TEXT DEFAULT 'ACTIVE',
            note TEXT DEFAULT '',
            last_job_id TEXT DEFAULT '',
            last_status TEXT DEFAULT '',
            last_used_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Jobs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
            id TEXT PRIMARY KEY,
            service TEXT NOT NULL,
            status TEXT DEFAULT 'PENDING',
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public static function getSetting(string $key, ?string $default = null): ?string {
        $stmt = self::get()->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    public static function setSetting(string $key, string $value): void {
        $stmt = self::get()->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
        $stmt->execute([$key, $value]);
    }

    public static function getAccount(string $username): ?array {
        $stmt = self::get()->prepare("SELECT * FROM accounts WHERE username = ? COLLATE NOCASE");
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAccountsByUsernames(array $usernames): array {
        if (empty($usernames)) return [];
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $cleaned = array_map('trim', $usernames);
        $stmt = self::get()->prepare("SELECT * FROM accounts WHERE username IN ($placeholders) COLLATE NOCASE");
        $stmt->execute($cleaned);
        $rows = $stmt->fetchAll();

        // Index by lowercase username
        $map = [];
        foreach ($rows as $row) {
            $map[strtolower($row['username'])] = $row;
        }
        return $map;
    }

    public static function upsertAccount(string $username, string $cookie, string $password = '', string $note = ''): bool {
        $username = trim($username);
        $cookie = trim($cookie);
        $password = trim($password);
        $note = trim($note);

        if (empty($username) || empty($cookie)) return false;

        $stmt = self::get()->prepare("
            INSERT INTO accounts (username, password, cookie, note)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(username) DO UPDATE SET
                cookie = excluded.cookie,
                password = CASE WHEN excluded.password != '' THEN excluded.password ELSE accounts.password END,
                note = CASE WHEN excluded.note != '' THEN excluded.note ELSE accounts.note END
        ");
        return $stmt->execute([$username, $password, $cookie, $note]);
    }

    public static function getAccountsCount(): int {
        return (int)self::get()->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
    }

    public static function listAccounts(string $search = '', int $limit = 20000): array {
        if (!empty($search)) {
            $stmt = self::get()->prepare("
                SELECT id, username, password, status, note, last_job_id, last_status, last_used_at, created_at,
                       substr(cookie, 1, 30) || '...' as cookie_preview
                FROM accounts
                WHERE username LIKE ? OR note LIKE ?
                ORDER BY id DESC LIMIT ?
            ");
            $like = '%' . $search . '%';
            $stmt->execute([$like, $like, $limit]);
        } else {
            $stmt = self::get()->prepare("
                SELECT id, username, password, status, note, last_job_id, last_status, last_used_at, created_at,
                       substr(cookie, 1, 30) || '...' as cookie_preview
                FROM accounts
                ORDER BY id DESC LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll();
    }

    public static function deleteAccount(int $id): bool {
        $stmt = self::get()->prepare("DELETE FROM accounts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function saveJob(array $data): bool {
        $stmt = self::get()->prepare("
            INSERT INTO jobs (id, service, status, priority, note, total_accounts, total_amount, accounts_json, raw_response, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ON CONFLICT(id) DO UPDATE SET
                status = excluded.status,
                total_accounts = excluded.total_accounts,
                total_amount = excluded.total_amount,
                updated_at = datetime('now')
        ");
        return $stmt->execute([
            $data['id'],
            $data['service'] ?? 'captcha',
            $data['status'] ?? 'PENDING',
            !empty($data['priority']) ? 1 : 0,
            $data['note'] ?? '',
            $data['total_accounts'] ?? 0,
            $data['total_amount'] ?? 0,
            json_encode($data['accounts'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($data['raw'] ?? [], JSON_UNESCAPED_UNICODE)
        ]);
    }

    public static function updateJobStatus(string $id, array $update): bool {
        $fields = [];
        $params = [];
        foreach (['status', 'total_amount', 'success_amount', 'fail_amount', 'skip_amount', 'refunded_amount', 'accounts_detail_json'] as $col) {
            if (isset($update[$col])) {
                $fields[] = "$col = ?";
                $params[] = is_array($update[$col]) ? json_encode($update[$col], JSON_UNESCAPED_UNICODE) : $update[$col];
            }
        }
        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $sql = "UPDATE jobs SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;

        $stmt = self::get()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function getJob(string $id): ?array {
        $stmt = self::get()->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $row['accounts'] = json_decode($row['accounts_json'] ?: '[]', true);
            $row['accounts_detail'] = json_decode($row['accounts_detail_json'] ?: '[]', true);
        }
        return $row ?: null;
    }

    public static function listJobs(int $limit = 30): array {
        $stmt = self::get()->prepare("SELECT * FROM jobs ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['accounts'] = json_decode($r['accounts_json'] ?: '[]', true);
            $r['accounts_detail'] = json_decode($r['accounts_detail_json'] ?: '[]', true);

            $sCount = 0;
            $fCount = 0;
            if (!empty($r['accounts_detail'])) {
                foreach ($r['accounts_detail'] as $item) {
                    $st = strtoupper($item['status'] ?? '');
                    if (in_array($st, ['COMPLETED', 'SUCCESS'])) $sCount++;
                    elseif (in_array($st, ['FAILED', 'COOKIE_BROKEN', 'FACE_LOCK'])) $fCount++;
                }
            }
            $r['success_count'] = $sCount;
            $r['fail_count'] = $fCount;
        }
        return $rows;
    }

    public static function updateAccountUsage(string $username, string $jobId, string $status = ''): void {
        $stmt = self::get()->prepare("
            UPDATE accounts
            SET last_used_at = datetime('now'),
                last_job_id = ?,
                last_status = CASE WHEN ? != '' THEN ? ELSE last_status END
            WHERE username = ? COLLATE NOCASE
        ");
        $stmt->execute([$jobId, $status, $status, trim($username)]);
    }
}
