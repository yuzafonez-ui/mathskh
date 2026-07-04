<?php
/**
 * database.php
 * ------------------------------------------------------------
 * Data-access layer for the "Maths KH" app.
 *
 * index.html currently keeps all of its state in the browser's
 * localStorage (mathskh_users, mathskh_tests, mathskh_ledSettings,
 * mathskh_certSettings, mathskh_appDetails). This file provides a
 * PHP/MySQL backend with the exact same shape of data, described
 * in database.sql, so the front-end can be wired up to a real
 * database later without changing index.html itself.
 *
 * Usage:
 *   require_once 'database.php';
 *   $db = new Database();
 *   $users = $db->getUsers();
 * ------------------------------------------------------------
 */

declare(strict_types=1);

class Database
{
    private PDO $pdo;

    // --- Connection settings: adjust to match your environment ---
    private string $host = '127.0.0.1';
    private string $dbName = 'mathskh';
    private string $dbUser = 'root';
    private string $dbPass = '';
    private string $charset = 'utf8mb4';

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
            return;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->dbUser, $this->dbPass, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /* =========================================================
     * USERS  (mirrors mathskh_users)
     * ========================================================= */

    /** Return all users, each with its certificates attached (like the JS `users` array). */
    public function getUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, full_name, role, created_at FROM users ORDER BY id');
        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            $user['certificates'] = $this->getCertificatesByUser((int) $user['id']);
        }

        return $users;
    }

    public function findUserByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        $user['certificates'] = $this->getCertificatesByUser((int) $user['id']);
        return $user;
    }

    /** Mirrors the register() JS function. Returns the new user's id. */
    public function registerUser(string $fullName, string $username, string $password): int
    {
        if ($this->findUserByUsername($username) !== null) {
            throw new InvalidArgumentException('ឈ្មោះនេះមានគេប្រើហើយ! (Username already exists)');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password, full_name, role) VALUES (:username, :password, :full_name, :role)'
        );
        $stmt->execute([
            'username'  => $username,
            'password'  => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $fullName,
            'role'      => 'student',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Mirrors the login() JS function. Returns the user array on success, or null. */
    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->findUserByUsername($username);
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        unset($user['password']);
        return $user;
    }

    /** Mirrors saveProfile()/updateProfile() editing fullName and optionally password. */
    public function updateUserProfile(string $username, string $fullName, ?string $newPassword = null): bool
    {
        if ($newPassword !== null && $newPassword !== '') {
            $stmt = $this->pdo->prepare(
                'UPDATE users SET full_name = :full_name, password = :password WHERE username = :username'
            );
            return $stmt->execute([
                'full_name' => $fullName,
                'password'  => password_hash($newPassword, PASSWORD_DEFAULT),
                'username'  => $username,
            ]);
        }

        $stmt = $this->pdo->prepare('UPDATE users SET full_name = :full_name WHERE username = :username');
        return $stmt->execute(['full_name' => $fullName, 'username' => $username]);
    }

    /* =========================================================
     * TESTS & EXERCISES  (mirrors mathskh_tests)
     * ========================================================= */

    /** Return all tests with their exercises nested, matching the JS `tests` array shape. */
    public function getTests(): array
    {
        $tests = $this->pdo->query('SELECT * FROM tests ORDER BY id')->fetchAll();
        foreach ($tests as &$test) {
            $test['exercises'] = $this->getExercisesForTest((int) $test['id']);
        }
        return $tests;
    }

    /** Mirrors `tests.filter(t => t.level === currentLevel)`. */
    public function getTestsByLevel(string $level): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tests WHERE level = :level ORDER BY id');
        $stmt->execute(['level' => $level]);
        $tests = $stmt->fetchAll();

        foreach ($tests as &$test) {
            $test['exercises'] = $this->getExercisesForTest((int) $test['id']);
        }
        return $tests;
    }

    public function getTestById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $test = $stmt->fetch();
        if ($test === false) {
            return null;
        }
        $test['exercises'] = $this->getExercisesForTest($id);
        return $test;
    }

    private function getExercisesForTest(int $testId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM exercises WHERE test_id = :test_id ORDER BY sort_order, id');
        $stmt->execute(['test_id' => $testId]);
        $rows = $stmt->fetchAll();

        // Reshape into the { question, options: {A,B,C,D}, correct, score, answer, ansUrl } shape used in JS.
        return array_map(static function (array $row): array {
            return [
                'question' => $row['question'],
                'options'  => [
                    'A' => $row['option_a'],
                    'B' => $row['option_b'],
                    'C' => $row['option_c'],
                    'D' => $row['option_d'],
                ],
                'correct' => $row['correct_option'],
                'score'   => $row['score'],
                'answer'  => $row['answer'],
                'ansUrl'  => $row['ans_url'],
            ];
        }, $rows);
    }

    /**
     * Mirrors saveTest() when creating a new test.
     * $exercises is an array of { question, options: {A,B,C,D}, correct, score, answer, ansUrl }.
     */
    public function createTest(string $level, string $title, int $examTimeLimit, array $exercises): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tests (level, title, exam_time_limit) VALUES (:level, :title, :exam_time_limit)'
            );
            $stmt->execute(['level' => $level, 'title' => $title, 'exam_time_limit' => $examTimeLimit]);
            $testId = (int) $this->pdo->lastInsertId();

            $this->insertExercises($testId, $exercises);

            $this->pdo->commit();
            return $testId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Mirrors saveTest() when editingTestId is set (replaces title/time/exercises). */
    public function updateTest(int $id, string $title, int $examTimeLimit, array $exercises): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tests SET title = :title, exam_time_limit = :exam_time_limit WHERE id = :id'
            );
            $stmt->execute(['title' => $title, 'exam_time_limit' => $examTimeLimit, 'id' => $id]);

            // Replace exercises wholesale, same effect as the JS which rebuilds outputExercises each save.
            $del = $this->pdo->prepare('DELETE FROM exercises WHERE test_id = :test_id');
            $del->execute(['test_id' => $id]);

            $this->insertExercises($id, $exercises);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insertExercises(int $testId, array $exercises): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO exercises
                (test_id, sort_order, question, option_a, option_b, option_c, option_d, correct_option, score, answer, ans_url)
             VALUES
                (:test_id, :sort_order, :question, :option_a, :option_b, :option_c, :option_d, :correct_option, :score, :answer, :ans_url)'
        );

        foreach (array_values($exercises) as $index => $ex) {
            $stmt->execute([
                'test_id'        => $testId,
                'sort_order'     => $index,
                'question'       => $ex['question'] ?? '',
                'option_a'       => $ex['options']['A'] ?? '',
                'option_b'       => $ex['options']['B'] ?? '',
                'option_c'       => $ex['options']['C'] ?? '',
                'option_d'       => $ex['options']['D'] ?? '',
                'correct_option' => $ex['correct'] ?? 'A',
                'score'          => $ex['score'] ?? 10,
                'answer'         => $ex['answer'] ?? '',
                'ans_url'        => $ex['ansUrl'] ?? '',
            ]);
        }
    }

    /** Mirrors deleteTest(). */
    public function deleteTest(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tests WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /* =========================================================
     * CERTIFICATES  (mirrors currentUser.certificates)
     * ========================================================= */

    public function getCertificatesByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT test_id AS testId, test_title AS testTitle, grade, awarded_at AS `date`
             FROM certificates WHERE user_id = :user_id ORDER BY awarded_at'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Mirrors the "Prevent duplicate certificates for the same test" logic in submitExam().
     * Returns true if a new certificate was inserted, false if one already existed.
     */
    public function addCertificateIfMissing(int $userId, int $testId, string $testTitle, string $grade): bool
    {
        $exists = $this->pdo->prepare(
            'SELECT 1 FROM certificates WHERE user_id = :user_id AND test_id = :test_id LIMIT 1'
        );
        $exists->execute(['user_id' => $userId, 'test_id' => $testId]);
        if ($exists->fetch() !== false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certificates (user_id, test_id, test_title, grade) VALUES (:user_id, :test_id, :test_title, :grade)'
        );
        return $stmt->execute([
            'user_id'    => $userId,
            'test_id'    => $testId,
            'test_title' => $testTitle,
            'grade'      => $grade,
        ]);
    }

    /* =========================================================
     * LED SETTINGS  (mirrors mathskh_ledSettings)
     * ========================================================= */

    public function getLedSettings(): array
    {
        $stmt = $this->pdo->query(
            'SELECT text, text_color AS textColor, bg_color AS bgColor FROM led_settings WHERE id = 1'
        );
        $row = $stmt->fetch();
        return $row !== false ? $row : ['text' => '', 'textColor' => '#ffffff', 'bgColor' => '#ff0000'];
    }

    public function saveLedSettings(string $text, string $textColor, string $bgColor): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO led_settings (id, text, text_color, bg_color) VALUES (1, :text, :text_color, :bg_color)
             ON DUPLICATE KEY UPDATE text = VALUES(text), text_color = VALUES(text_color), bg_color = VALUES(bg_color)'
        );
        return $stmt->execute(['text' => $text, 'text_color' => $textColor, 'bg_color' => $bgColor]);
    }

    /* =========================================================
     * CERTIFICATE (LAYOUT) SETTINGS  (mirrors mathskh_certSettings)
     * ========================================================= */

    public function getCertSettings(): array
    {
        $stmt = $this->pdo->query(
            'SELECT logo_url AS logoUrl, logo_data AS logoData, border_data AS borderData,
                    sig_data AS sigData, description_template AS descriptionTemplate, signature
             FROM cert_settings WHERE id = 1'
        );
        $row = $stmt->fetch();
        return $row !== false ? $row : [
            'logoUrl' => '', 'logoData' => '', 'borderData' => '', 'sigData' => '',
            'descriptionTemplate' => '', 'signature' => '',
        ];
    }

    public function saveCertSettings(array $settings): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cert_settings (id, logo_url, logo_data, border_data, sig_data, description_template, signature)
             VALUES (1, :logo_url, :logo_data, :border_data, :sig_data, :description_template, :signature)
             ON DUPLICATE KEY UPDATE
                logo_url = VALUES(logo_url), logo_data = VALUES(logo_data), border_data = VALUES(border_data),
                sig_data = VALUES(sig_data), description_template = VALUES(description_template), signature = VALUES(signature)'
        );
        return $stmt->execute([
            'logo_url'              => $settings['logoUrl'] ?? '',
            'logo_data'             => $settings['logoData'] ?? '',
            'border_data'           => $settings['borderData'] ?? '',
            'sig_data'              => $settings['sigData'] ?? '',
            'description_template'  => $settings['descriptionTemplate'] ?? '',
            'signature'             => $settings['signature'] ?? '',
        ]);
    }

    /* =========================================================
     * APP DETAILS  (mirrors mathskh_appDetails + mathskh_color)
     * ========================================================= */

    public function getAppDetails(): array
    {
        $stmt = $this->pdo->query(
            'SELECT app_name AS app, features, levels, version, theme_color AS themeColor FROM app_details WHERE id = 1'
        );
        $row = $stmt->fetch();
        return $row !== false ? $row : [
            'app' => 'Maths KH', 'features' => '', 'levels' => '', 'version' => '1.0.0', 'themeColor' => 'navy',
        ];
    }

    public function saveAppDetails(array $details): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_details (id, app_name, features, levels, version, theme_color)
             VALUES (1, :app_name, :features, :levels, :version, :theme_color)
             ON DUPLICATE KEY UPDATE
                app_name = VALUES(app_name), features = VALUES(features),
                levels = VALUES(levels), version = VALUES(version), theme_color = VALUES(theme_color)'
        );
        return $stmt->execute([
            'app_name'    => $details['app'] ?? 'Maths KH',
            'features'    => $details['features'] ?? '',
            'levels'      => $details['levels'] ?? '',
            'version'     => $details['version'] ?? '1.0.0',
            'theme_color' => $details['themeColor'] ?? 'navy',
        ]);
    }

    public function saveThemeColor(string $color): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_details (id, theme_color) VALUES (1, :theme_color)
             ON DUPLICATE KEY UPDATE theme_color = VALUES(theme_color)'
        );
        return $stmt->execute(['theme_color' => $color]);
    }
}
