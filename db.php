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

            $dsn = 'pgsql:host=ep-crimson-credit-arkqmq69-pooler.c-4.us-west-2.aws.neon.tech;port=5432;dbname=neondb;sslmode=require';
            self::$pdo = new PDO($dsn, 'neondb_owner', 'npg_pTE1Uzgekdw3');
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
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('queue_mode', 'normal') ON CONFLICT (key) DO NOTHING");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('admin_password', 'admin1234') ON CONFLICT (key) DO NOTHING");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('zp_api_key', '') ON CONFLICT (key) DO NOTHING");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('tw_phone', '') ON CONFLICT (key) DO NOTHING");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('face_scan_cost', '1') ON CONFLICT (key) DO NOTHING");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('inw_api_key', '') ON CONFLICT (key) DO NOTHING");
        $pdo->exec("INSERT INTO settings (key, value) VALUES ('captcha_cost_per_account', '1') ON CONFLICT (key) DO NOTHING");

        // Members table (for Face Unlock access control)
        $pdo->exec("CREATE TABLE IF NOT EXISTS members (
            id SERIAL PRIMARY KEY,
            email TEXT UNIQUE NOT NULL ,
            password_hash TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            credits INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Add credits column to existing members table if it doesn't exist
        try { $pdo->exec("ALTER TABLE members ADD COLUMN credits INTEGER DEFAULT 0"); } catch (\Exception $e) {}

        // Topup log table
        $pdo->exec("CREATE TABLE IF NOT EXISTS topup_log (
            id SERIAL PRIMARY KEY,
            member_id INTEGER NOT NULL,
            email TEXT NOT NULL,
            link TEXT NOT NULL,
            amount_thb REAL NOT NULL,
            credits_added INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // PromptPay transaction table (to prevent double crediting)
        $pdo->exec("CREATE TABLE IF NOT EXISTS promptpay_tx (
            transaction_id TEXT PRIMARY KEY,
            member_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            credits_added INTEGER NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Accounts table
        $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
            id SERIAL PRIMARY KEY,
            username TEXT UNIQUE NOT NULL ,
            password TEXT DEFAULT '',
            cookie TEXT NOT NULL,
            status TEXT DEFAULT 'ACTIVE',
            note TEXT DEFAULT '',
            account_type TEXT DEFAULT 'shop',
            last_job_id TEXT DEFAULT '',
            last_status TEXT DEFAULT '',
            last_used_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Migrate existing accounts: add account_type column if not exist (existing rows become 'shop')
        try { $pdo->exec("ALTER TABLE accounts ADD COLUMN account_type TEXT DEFAULT 'shop'"); } catch (\Exception $e) {}

        // Migrate jobs table for refunds
        try { $pdo->exec("ALTER TABLE jobs ADD COLUMN member_id INTEGER DEFAULT 0"); } catch (\Exception $e) {}
        try { $pdo->exec("ALTER TABLE jobs ADD COLUMN cost_per_account INTEGER DEFAULT 0"); } catch (\Exception $e) {}
        try { $pdo->exec("ALTER TABLE jobs ADD COLUMN refunded_usernames TEXT DEFAULT '[]'"); } catch (\Exception $e) {}

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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
        $username = strtolower($username);
        $stmt = self::get()->prepare("SELECT * FROM accounts WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAccountsByUsernames(array $usernames): array {
        if (empty($usernames)) return [];
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $cleaned = array_map('strtolower', array_map('trim', $usernames));
        $stmt = self::get()->prepare("SELECT * FROM accounts WHERE LOWER(username) IN ($placeholders)");
        $stmt->execute($cleaned);
        $rows = $stmt->fetchAll();

        // Index by lowercase username
        $map = [];
        foreach ($rows as $row) {
            $map[strtolower($row['username'])] = $row;
        }
        return $map;
    }

    public static function upsertAccount(string $username, string $cookie, string $password = '', string $note = '', string $accountType = 'shop'): bool {
        $username = strtolower($username);
        $username = trim($username);
        $cookie = trim($cookie);
        $password = trim($password);
        $note = trim($note);
        $accountType = in_array($accountType, ['shop', 'external']) ? $accountType : 'shop';

        if (empty($username) || empty($cookie)) return false;

        $stmt = self::get()->prepare("
            INSERT INTO accounts (username, password, cookie, note, account_type)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(username) DO UPDATE SET
                cookie = excluded.cookie,
                password = CASE WHEN excluded.password != '' THEN excluded.password ELSE accounts.password END,
                note = CASE WHEN excluded.note != '' THEN excluded.note ELSE accounts.note END
        ");
        // NOTE: account_type is NOT updated on conflict — preserving the original type
        return $stmt->execute([$username, $password, $cookie, $note, $accountType]);
    }

    public static function setAccountType(int $id, string $accountType): bool {
        $accountType = in_array($accountType, ['shop', 'external']) ? $accountType : 'shop';
        $stmt = self::get()->prepare("UPDATE accounts SET account_type = ? WHERE id = ?");
        return $stmt->execute([$accountType, $id]);
    }

    public static function getAccountsCount(): int {
        return (int)self::get()->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
    }

    public static function clearAllAccounts(): bool {
        return (bool)self::get()->exec("DELETE FROM accounts");
    }

    public static function listAccounts(string $search = '', int $limit = 20000): array {
        if (!empty($search)) {
            $stmt = self::get()->prepare("
                SELECT id, username, password, status, note, account_type, last_job_id, last_status, last_used_at, created_at,
                       substr(cookie, 1, 30) || '...' as cookie_preview
                FROM accounts
                WHERE username LIKE ? OR note LIKE ?
                ORDER BY id DESC LIMIT ?
            ");
            $like = '%' . $search . '%';
            $stmt->execute([$like, $like, $limit]);
        } else {
            $stmt = self::get()->prepare("
                SELECT id, username, password, status, note, account_type, last_job_id, last_status, last_used_at, created_at,
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
            INSERT INTO jobs (id, service, status, priority, note, total_accounts, total_amount, accounts_json, raw_response, member_id, cost_per_account, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(id) DO UPDATE SET
                status = excluded.status,
                total_accounts = excluded.total_accounts,
                total_amount = excluded.total_amount,
                updated_at = CURRENT_TIMESTAMP
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
            json_encode($data['raw'] ?? [], JSON_UNESCAPED_UNICODE),
            $data['member_id'] ?? 0,
            $data['cost_per_account'] ?? 0
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

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
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
            $skCount = 0;
            if (!empty($r['accounts_detail'])) {
                foreach ($r['accounts_detail'] as $item) {
                    $st = strtoupper(trim($item['status'] ?? ''));
                    if (in_array($st, ['COMPLETED', 'SUCCESS'])) $sCount++;
                    elseif (in_array($st, ['SKIP', 'SKIPPED', 'NO_CAPTCHA'])) $skCount++;
                    elseif (in_array($st, ['FAILED', 'FAIL', 'ERROR', 'COOKIE_BROKEN', 'FACE_LOCK', 'FACELOCK', 'WRONG_PASSWORD', 'INVALID', 'INV', 'TWO_STEP', '2STEP', '2FA', 'BANNED', 'BAN'])) $fCount++;
                    elseif (!empty($st) && !in_array($st, ['PENDING', 'PROCESSING', 'QUEUED'])) $fCount++;
                }
            }
            $r['success_count'] = $sCount;
            $r['fail_count'] = $fCount;
            $r['skip_count'] = $skCount;
        }
        return $rows;
    }

    public static function updateAccountUsage(string $username, string $jobId, string $status = ''): void {
        $username = strtolower($username);
        $stmt = self::get()->prepare("
            UPDATE accounts
            SET last_used_at = CURRENT_TIMESTAMP,
                last_job_id = ?,
                last_status = CASE WHEN ? != '' THEN ? ELSE last_status END
            WHERE LOWER(username) = LOWER(?)
        ");
        $stmt->execute([$jobId, $status, $status, trim($username)]);
    }

    public static function updateAccountStatus(string $username, string $status): void {
        $username = strtolower($username);
        $stmt = self::get()->prepare("
            UPDATE accounts
            SET last_status = ?
            WHERE LOWER(username) = LOWER(?)
        ");
        $stmt->execute([trim($status), trim($username)]);
    }

    // ========== MEMBER METHODS (Face Unlock access control) ==========

    public static function getMemberByEmail(string $email): ?array {
        $email = strtolower($email);
        $stmt = self::get()->prepare("SELECT * FROM members WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([trim($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getMemberById(int $id): ?array {
        $stmt = self::get()->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createMember(string $email, string $passwordHash, string $status = 'pending'): bool {
        $email = strtolower($email);
        $stmt = self::get()->prepare("
            INSERT INTO members (email, password_hash, status)
            VALUES (?, ?, ?) ON CONFLICT (email) DO NOTHING
        ");
        return $stmt->execute([trim($email), $passwordHash, $status]);
    }

    public static function updateMemberStatus(int $id, string $status): bool {
        $stmt = self::get()->prepare("
            UPDATE members SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }

    public static function listMembers(): array {
        return self::get()->query("SELECT id, email, status, credits, created_at, updated_at FROM members ORDER BY id DESC")->fetchAll();
    }

    public static function deleteMember(int $id): bool {
        $stmt = self::get()->prepare("DELETE FROM members WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ========== CREDITS METHODS ==========

    public static function getMemberCredits(int $id): int {
        $stmt = self::get()->prepare("SELECT credits FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? (int)$row['credits'] : 0;
    }

    public static function addMemberCredits(int $id, int $amount): bool {
        $stmt = self::get()->prepare("UPDATE members SET credits = credits + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$amount, $id]);
    }

    public static function deductMemberCredits(int $id, int $amount): bool {
        $stmt = self::get()->prepare("UPDATE members SET credits = GREATEST(0, credits - ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$amount, $id]);
    }

    public static function setMemberCredits(int $id, int $amount): bool {
        $stmt = self::get()->prepare("UPDATE members SET credits = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([max(0, $amount), $id]);
    }

    // ========== TOPUP LOG METHODS ==========

    public static function logTopup(int $memberId, string $email, string $link, float $amountThb, int $creditsAdded): bool {
        $stmt = self::get()->prepare("
            INSERT INTO topup_log (member_id, email, link, amount_thb, credits_added)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$memberId, $email, $link, $amountThb, $creditsAdded]);
    }

    public static function createPromptpayTx(string $txId, int $memberId, float $amount, int $creditsAdded): bool {
        $stmt = self::get()->prepare("
            INSERT INTO promptpay_tx (transaction_id, member_id, amount, credits_added, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$txId, $memberId, $amount, $creditsAdded]);
    }

    public static function getPromptpayTx(string $txId): ?array {
        $stmt = self::get()->prepare("SELECT * FROM promptpay_tx WHERE transaction_id = ?");
        $stmt->execute([$txId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updatePromptpayTxStatus(string $txId, string $status): bool {
        $stmt = self::get()->prepare("UPDATE promptpay_tx SET status = ? WHERE transaction_id = ?");
        return $stmt->execute([$status, $txId]);
    }

    public static function getTopupLog(int $limit = 200): array {
        $stmt = self::get()->prepare("SELECT * FROM topup_log ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function checkVoucherUsed(string $link): bool {
        $stmt = self::get()->prepare("SELECT id FROM topup_log WHERE link = ? LIMIT 1");
        $stmt->execute([trim($link)]);
        return (bool)$stmt->fetch();
    }
}

