
<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}


function icon_svg(string $name): string
{
    static $icons = [
        'message' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>',

        'book-open' => '<path d="M12 7v14"/><path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-4a3 3 0 0 0-3 3 3 3 0 0 0-3-3H5a2 2 0 0 1-2-2z"/>',

        'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m8 12 2.5 2.5L16 9"/>',

        'lightbulb' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14a6 6 0 1 0-6.2 0c.6.4 1.1 1.1 1.1 2h4c0-.9.5-1.6 1.1-2z"/>',

        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',

        'trophy' => '<path d="M8 21h8m-4-4v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M7 6H4v2a4 4 0 0 0 4 4m9-6h3v2a4 4 0 0 1-4 4"/>',

        'bookmark' => '<path d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-4-6 4V4z"/>',

        'thumbs-up' => '<path d="M7 10v12"/><path d="M15 5.9 14 10h6a2 2 0 0 1 2 2l-1.5 8a2 2 0 0 1-2 2H7V10l4.2-7a2 2 0 0 1 3.8 1.1z"/><path d="M3 10h4v12H3z"/>',

        'thumbs-down' => '<path d="M7 14V2"/><path d="M15 18.1 14 14h6a2 2 0 0 0 2-2l-1.5-8a2 2 0 0 0-2-2H7v12l4.2 7a2 2 0 0 0 3.8-1.1z"/><path d="M3 2h4v12H3z"/>',

        'alert-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>',

        'award' => '<circle cx="12" cy="8" r="6"/><path d="m8.2 13-1.4 9 5.2-3 5.2 3-1.4-9"/>',

        'info-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 11v5m0-8h.01"/>',

        'pen-line' => '<path d="m12 20 9-9-4-4-9 9-1 5z"/><path d="m14 6 4 4M4 20h5"/>',

        'sparkles' => '<path d="m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-2-5.8L4 11l6-2.2L12 3z"/><path d="m19 14 1.2 2.8L23 18l-2.8 1.2L19 22l-1.2-2.8L15 18l2.8-1.2L19 14z"/>',

        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',

        'briefcase' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18m-11 0v2h4v-2"/>',

        'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',

        'admissions' => '<path d="M7 17 17 7M8 7h9v9"/>',

        'academics' => '<path d="M12 7v14"/><path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-4a3 3 0 0 0-3 3 3 3 0 0 0-3-3H5a2 2 0 0 1-2-2z"/>',

        'placements' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18m-11 0v2h4v-2"/>',

        'campus-life' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
    ];

    $paths = $icons[$name] ?? '<circle cx="12" cy="12" r="9"/>';

    return '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $paths
        . '</svg>';
}


function e(?string $v): string
{
    return htmlspecialchars(
        $v ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token())
        . '">';
}


function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(419);
        exit('Your form session expired. Refresh the page and try again.');
    }
}


function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}


function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}


function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}


function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $statement = db()->prepare(
            'SELECT id, full_name, email, role, department,
                    graduation_year, affiliation_verified, status
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        $statement->execute([(int) $_SESSION['user_id']]);
        $user = $statement->fetch();

        if (!$user || $user['status'] !== 'active') {
            unset($_SESSION['user_id']);
            return null;
        }

        return $user;
    } catch (Throwable $exception) {
        return null;
    }
}


function require_login(): array
{
    $user = current_user();

    if (!$user) {
        set_flash('error', 'Please log in to access that page.');
        redirect('login.php');
    }

    return $user;
}


function require_role(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);

        $pageTitle = 'Access denied';

        require __DIR__ . '/header.php';

        echo '<section class="section-block">
                <div class="empty-state">
                    <h3>Access denied</h3>
                    <p>Your account does not have permission to access this area.</p>
                    <a class="btn btn-primary" href="index.php">Return home</a>
                </div>
              </section>';

        require __DIR__ . '/footer.php';
        exit;
    }

    return $user;
}


function time_ago(string $d): string
{
    $timestamp = strtotime($d);
    $elapsed = max(0, time() - $timestamp);

    if ($elapsed < 3600) {
        return max(1, (int) ($elapsed / 60)) . 'm ago';
    }

    if ($elapsed < 86400) {
        return (int) ($elapsed / 3600) . 'h ago';
    }

    if ($elapsed < 604800) {
        return (int) ($elapsed / 86400) . 'd ago';
    }

    return date('M j', $timestamp);
}


function shorten(string $t, int $l): string
{
    if (function_exists('mb_strlen') && mb_strlen($t) > $l) {
        return mb_substr($t, 0, $l - 1) . '…';
    }

    return strlen($t) > $l
        ? substr($t, 0, $l - 1) . '…'
        : $t;
}


function get_categories(): array
{
    return db()->query(
        'SELECT id, name, slug, description, icon, color
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order, name'
    )->fetchAll();
}


function get_recent_questions(int $limit = 5): array
{
    $statement = db()->prepare(
        'SELECT
            q.id,
            q.title,
            q.body,
            q.created_at,
            c.name AS category_name,
            COALESCE(u.full_name, "Community member") AS author_name,
            (
                SELECT COUNT(*)
                FROM answers a
                WHERE a.question_id = q.id
                  AND a.status = "published"
            ) AS answer_count
         FROM questions q
         LEFT JOIN categories c ON c.id = q.category_id
         LEFT JOIN users u ON u.id = q.user_id
         WHERE q.status = "published"
           AND q.moderation_status = "allowed"
         ORDER BY q.created_at DESC
         LIMIT :lim'
    );

    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}


function search_questions(string $q = '', int $cat = 0): array
{
    $sql = 'SELECT
                q.id,
                q.title,
                q.body,
                q.created_at,
                c.name AS category_name,
                COALESCE(u.full_name, "Community member") AS author_name,
                (
                    SELECT COUNT(*)
                    FROM answers a
                    WHERE a.question_id = q.id
                      AND a.status = "published"
                ) AS answer_count
            FROM questions q
            LEFT JOIN categories c ON c.id = q.category_id
            LEFT JOIN users u ON u.id = q.user_id
            WHERE q.status = "published"
              AND q.moderation_status = "allowed"';

    $params = [];

    if ($q !== '') {
        $sql .= ' AND (q.title LIKE :s1 OR q.body LIKE :s2)';

        $params[':s1'] = '%' . $q . '%';
        $params[':s2'] = '%' . $q . '%';
    }

    if ($cat > 0) {
        $sql .= ' AND q.category_id = :cat';
        $params[':cat'] = $cat;
    }

    $sql .= ' ORDER BY q.created_at DESC LIMIT 100';

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}


function get_announcements(int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT title, body, label, created_at
         FROM announcements
         WHERE is_published = 1
         ORDER BY created_at DESC
         LIMIT :lim'
    );

    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}


function audit_log(
    int $actorId,
    string $action,
    string $entityType,
    ?int $entityId = null,
    string $details = ''
): void {
    try {
        $statement = db()->prepare(
            'INSERT INTO admin_audit_logs (
                actor_id,
                action,
                entity_type,
                entity_id,
                details,
                ip_address,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        $statement->execute([
            $actorId,
            substr($action, 0, 100),
            substr($entityType, 0, 50),
            $entityId,
            substr($details, 0, 2000),
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $exception) {
        error_log(
            'AITAM SLACK audit log failed: ' . $exception->getMessage()
        );
    }
}
