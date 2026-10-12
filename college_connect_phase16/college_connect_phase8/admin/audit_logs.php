
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'Admin audit log';

// Get filter parameters
$action = trim((string) ($_GET['action'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];

// Filter by action
if ($action !== '') {
    $where[] = 'l.action LIKE ?';
    $params[] = '%' . $action . '%';
}

// Search by administrator, entity type, or details
if ($search !== '') {
    $where[] = '(
        u.full_name LIKE ?
        OR u.email LIKE ?
        OR l.entity_type LIKE ?
        OR l.details LIKE ?
    )';

    array_push(
        $params,
        '%' . $search . '%',
        '%' . $search . '%',
        '%' . $search . '%',
        '%' . $search . '%'
    );
}

// Build the audit log query
$sql = '
    SELECT
        l.*,
        u.full_name,
        u.email
    FROM admin_audit_logs l
    LEFT JOIN users u
        ON u.id = l.actor_id
';

// Apply filters when provided
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// Sort newest records first
$sql .= '
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 300
';

// Execute query
$stmt = db()->prepare($sql);
$stmt->execute($params);

$logs = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">

    <span class="eyebrow">ACCOUNTABILITY</span>

    <h1>Admin audit log</h1>

    <p>
        Review recorded administrative decisions, including
        appeal outcomes. Up to 300 matching records are shown.
    </p>

</section>

<section class="section-block">

    <!-- Audit log filters -->
    <form
        method="get"
        class="account-card report-status-form"
        style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;"
    >

        <div style="flex: 1; min-width: 180px;">

            <label for="action">Action contains</label>

            <input
                id="action"
                name="action"
                value="<?= e($action) ?>"
                placeholder="e.g. appeal_approved"
            >

        </div>

        <div style="flex: 2; min-width: 220px;">

            <label for="q">
                Search admin, entity or details
            </label>

            <input
                id="q"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Name, email, appeal ID..."
            >

        </div>

        <button class="btn btn-primary" type="submit">
            Filter
        </button>

        <a
            class="btn btn-secondary"
            href="audit_logs.php"
        >
            Reset
        </a>

    </form>

    <!-- Audit log table -->
    <div class="account-card table-card">

        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>

                <tbody>

                    <!-- Empty state -->
                    <?php if (!$logs): ?>
                        <tr>
                            <td colspan="6">
                                No audit records found.
                                New appeal decisions will appear
                                after Phase 8 migration is applied.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <!-- Display audit records -->
                    <?php foreach ($logs as $log): ?>

                        <tr>

                            <td>
                                <?= e($log['created_at']) ?>
                            </td>

                            <td>
                                <?= e(
                                    $log['full_name']
                                        ?? 'Deleted account'
                                ) ?>

                                <br>

                                <small>
                                    <?= e($log['email'] ?? '') ?>
                                </small>
                            </td>

                            <td>
                                <span class="status-chip">
                                    <?= e($log['action']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($log['entity_type']) ?>

                                <?= $log['entity_id'] !== null
                                    ? ' #' . (int) $log['entity_id']
                                    : '' ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    e($log['details'] ?? '')
                                ) ?>
                            </td>

                            <td>
                                <?= e($log['ip_address'] ?? '') ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
