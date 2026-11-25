
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
    $where[] = 'l.action LIKE :audit_action';
    $params[':audit_action'] = '%' . $action . '%';
}

// Search by administrator, entity type, or details
if ($search !== '') {
    $where[] = '(
        u.full_name LIKE :audit_name
        OR u.email LIKE :audit_email
        OR l.entity_type LIKE :audit_entity
        OR l.details LIKE :audit_details
    )';

    $params[':audit_name'] = '%' . $search . '%';
    $params[':audit_email'] = '%' . $search . '%';
    $params[':audit_entity'] = '%' . $search . '%';
    $params[':audit_details'] = '%' . $search . '%';
}

// Build the audit log query
// Apply filters when provided
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countSql = '
    SELECT COUNT(*)
    FROM admin_audit_logs l
    LEFT JOIN users u ON u.id = l.actor_id
' . $whereSql;
$recordsSql = '
    SELECT
        l.action,
        l.entity_type,
        l.entity_id,
        l.details,
        l.ip_address,
        l.created_at,
        u.full_name,
        u.email
    FROM admin_audit_logs l
    LEFT JOIN users u ON u.id = l.actor_id
' . $whereSql . '
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT :pagination_limit OFFSET :pagination_offset
';

$logResult = getPaginatedRecords(
    $countSql,
    $recordsSql,
    $params,
    25,
    [10, 20, 25, 50, 100]
);
$logs = $logResult['records'];
$pagination = $logResult['pagination'];

require __DIR__ . '/../includes/header.php';
?>


<section class="page-hero">

    <span class="eyebrow">ACCOUNTABILITY</span>

    <h1>Admin audit log</h1>

    <p>
        Review recorded administrative decisions, including appeal outcomes.
    </p>

</section>
<?php include __DIR__ . '/admin-nav.php'; ?>
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

        <input
            type="hidden"
            name="per_page"
            value="<?= (int) $pagination['per_page'] ?>"
        >

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

    <?= renderPagination($pagination, 'page', 'per_page', 'Audit records') ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
