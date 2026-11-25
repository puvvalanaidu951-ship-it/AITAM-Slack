<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);

$pageTitle = 'Reports';

<<<<<<< HEAD
/*
|--------------------------------------------------------------------------
| Detect available user name column
|--------------------------------------------------------------------------
| The reports table uses user_id, not reporter_id.
| This checks the users table to find the correct name column.
*/

$userColumns = db()->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

$nameColumn = null;

foreach (['full_name', 'name', 'username', 'email'] as $candidate) {
    if (in_array($candidate, $userColumns, true)) {
        $nameColumn = $candidate;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Handle report status updates
|--------------------------------------------------------------------------
*/

=======
$reportTypes = [
    'question' => 'Inappropriate Question',
    'answer' => 'Inappropriate Answer',
    'user' => 'Report a User',
    'spam' => 'Spam or Fake Content',
    'harassment' => 'Harassment or Abuse',
    'technical' => 'Technical Issue',
    'other' => 'Other',
];
$reportStatuses = [
    'pending' => 'Pending',
    'reviewing' => 'Under review',
    'resolved' => 'Resolved',
    'rejected' => 'Rejected',
];

function cc_admin_reports_redirect(bool $clearDetails = false): never
{
    $requestedPage = filter_var(
        $_GET['page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $params = $clearDetails ? ['id' => null] : [];
    redirect(
        'reports.php' . buildPaginationUrl(
            $requestedPage === false ? 1 : $requestedPage,
            $params,
            'page'
        )
    );
}

>>>>>>> 56882bb9f493ac3e31ab078983b6cb59019105f4
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $idInput = $_POST['id'] ?? '';
    $reportId = is_string($idInput)
        ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';

<<<<<<< HEAD
    $status = (string) ($_POST['status'] ?? '');

    $allowedStatuses = [
        'pending',
        'reviewing',
        'resolved',
        'rejected'
    ];

    if ($id > 0 && in_array($status, $allowedStatuses, true)) {

        db()->prepare(
            'UPDATE reports
             SET status = ?,
                 reviewed_by = ?,
                 reviewed_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        )->execute([
            $status,
            (int) $admin['id'],
            $id
        ]);

        set_flash('success', 'Report updated successfully.');

    } else {

        set_flash('error', 'Invalid report or status.');

=======
    if ($reportId === false) {
        set_flash('error', 'Select a valid report.');
        cc_admin_reports_redirect();
>>>>>>> 56882bb9f493ac3e31ab078983b6cb59019105f4
    }

    if (!in_array($action, ['review', 'resolve', 'reject', 'save_notes', 'delete'], true)) {
        set_flash('error', 'Select a valid report action.');
        cc_admin_reports_redirect();
    }

    $rejectionReason = '';
    if ($action === 'reject') {
        $reasonInput = $_POST['rejection_reason'] ?? '';
        $rejectionReason = is_string($reasonInput) ? trim($reasonInput) : '';
        if (
            !mb_check_encoding($rejectionReason, 'UTF-8')
            || mb_strlen($rejectionReason) < 10
            || mb_strlen($rejectionReason) > 5000
        ) {
            set_flash('error', 'A rejection explanation of 10 to 5,000 characters is required.');
            cc_admin_reports_redirect();
        }
    }

    $adminNotes = '';
    if ($action === 'save_notes') {
        $notesInput = $_POST['admin_notes'] ?? '';
        if (!is_string($notesInput)) {
            set_flash('error', 'Internal notes must be submitted as text.');
            cc_admin_reports_redirect();
        }
        $adminNotes = trim($notesInput);
        if (!mb_check_encoding($adminNotes, 'UTF-8') || mb_strlen($adminNotes) > 10000) {
            set_flash('error', 'Internal notes must be valid text of 10,000 characters or fewer.');
            cc_admin_reports_redirect();
        }
    }

    try {
        $exists = db()->prepare('SELECT id FROM reports WHERE id = ? LIMIT 1');
        $exists->execute([(int) $reportId]);
        if (!$exists->fetchColumn()) {
            set_flash('error', 'That report no longer exists.');
            cc_admin_reports_redirect();
        }

        switch ($action) {
            case 'review':
                db()->prepare(
                    'UPDATE reports
                     SET status = "reviewing",
                         rejection_reason = NULL,
                         reviewed_by = ?,
                         reviewed_at = CURRENT_TIMESTAMP,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([(int) $admin['id'], (int) $reportId]);
                set_flash('success', 'Report marked under review.');
                break;

            case 'resolve':
                db()->prepare(
                    'UPDATE reports
                     SET status = "resolved",
                         rejection_reason = NULL,
                         reviewed_by = ?,
                         reviewed_at = CURRENT_TIMESTAMP,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([(int) $admin['id'], (int) $reportId]);
                set_flash('success', 'Report resolved.');
                break;

            case 'reject':
                db()->prepare(
                    'UPDATE reports
                     SET status = "rejected",
                         rejection_reason = ?,
                         reviewed_by = ?,
                         reviewed_at = CURRENT_TIMESTAMP,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([
                    $rejectionReason,
                    (int) $admin['id'],
                    (int) $reportId,
                ]);
                set_flash('success', 'Report rejected with an explanation.');
                break;

            case 'save_notes':
                db()->prepare(
                    'UPDATE reports
                     SET admin_notes = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([$adminNotes, (int) $reportId]);
                set_flash('success', 'Internal notes saved.');
                break;

            case 'delete':
                db()->prepare('DELETE FROM reports WHERE id = ?')
                    ->execute([(int) $reportId]);
                set_flash('success', 'Report deleted.');
                break;
        }
    } catch (PDOException $error) {
        error_log('Admin report action failed: ' . $error->getMessage());
        set_flash('error', 'The report could not be updated. Please try again.');
    }

    cc_admin_reports_redirect($action === 'delete');
}

<<<<<<< HEAD
/*
|--------------------------------------------------------------------------
| Fetch latest reports
|--------------------------------------------------------------------------
*/

if ($nameColumn !== null) {

    // Safe because the column name is selected from a fixed whitelist.
    $reports = db()->query(
        "SELECT
            r.*,
            COALESCE(
                NULLIF(u.`$nameColumn`, ''),
                CONCAT('User #', r.user_id)
            ) AS reporter
         FROM reports r
         LEFT JOIN users u ON u.id = r.user_id
         ORDER BY
            FIELD(
                r.status,
                'pending',
                'reviewing',
                'resolved',
                'rejected'
            ),
            r.created_at DESC
         LIMIT 200"
    )->fetchAll();

} else {

    // Fallback if the users table has no recognized name column.
    $reports = db()->query(
        "SELECT
            r.*,
            CONCAT('User #', r.user_id) AS reporter
         FROM reports r
         ORDER BY
            FIELD(
                r.status,
                'pending',
                'reviewing',
                'resolved',
                'rejected'
            ),
            r.created_at DESC
         LIMIT 200"
    )->fetchAll();

}

/*
|--------------------------------------------------------------------------
| Display page
|--------------------------------------------------------------------------
*/
=======
$searchInput = $_GET['q'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$search = mb_substr($search, 0, 120);

$categoryInput = $_GET['category'] ?? '';
$category = is_string($categoryInput) && isset($reportTypes[$categoryInput])
    ? $categoryInput
    : '';

$statusInput = $_GET['status'] ?? '';
$status = is_string($statusInput) && isset($reportStatuses[$statusInput])
    ? $statusInput
    : '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(
        CAST(r.id AS CHAR) LIKE :report_id
        OR u.full_name LIKE :reporter_name
        OR r.subject LIKE :report_subject
        OR r.description LIKE :report_description
    )';
    $searchValue = '%' . $search . '%';
    $params = [
        ':report_id' => $searchValue,
        ':reporter_name' => $searchValue,
        ':report_subject' => $searchValue,
        ':report_description' => $searchValue,
    ];
}
if ($category !== '') {
    $where[] = 'r.report_type = :report_category';
    $params[':report_category'] = $category;
}
if ($status !== '') {
    $where[] = 'r.status = :report_status';
    $params[':report_status'] = $status;
}

$fromSql = '
    FROM reports r
    LEFT JOIN users u ON u.id = r.user_id
';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$reportResult = getPaginatedRecords(
    'SELECT COUNT(*) ' . $fromSql . $whereSql,
    'SELECT
        r.id,
        r.user_id,
        r.report_type,
        r.reported_item_id,
        r.subject,
        r.description,
        r.admin_notes,
        r.rejection_reason,
        r.status,
        r.created_at,
        r.updated_at,
        r.reviewed_at,
        COALESCE(u.full_name, "Deleted account") AS reporter_name
     ' . $fromSql . $whereSql . '
     ORDER BY
        FIELD(r.status, "pending", "reviewing", "resolved", "rejected"),
        r.created_at DESC,
        r.id DESC
     LIMIT :pagination_limit OFFSET :pagination_offset',
    $params,
    20,
    [10, 20, 50, 100]
);
$reports = $reportResult['records'];
$pagination = $reportResult['pagination'];

$reportStats = db()->query(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "pending") AS pending,
        SUM(status = "reviewing") AS reviewing,
        SUM(status = "resolved") AS resolved,
        SUM(status = "rejected") AS rejected
     FROM reports'
)->fetch();

$detailIdInput = $_GET['id'] ?? null;
$detailId = is_string($detailIdInput)
    ? filter_var($detailIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$reportDetail = null;
$detailError = '';
if ($detailIdInput !== null) {
    if ($detailId === false) {
        $detailError = 'Invalid report ID.';
    } else {
        $detailStatement = db()->prepare(
            'SELECT
                r.id,
                r.user_id,
                r.report_type,
                r.reported_item_id,
                r.subject,
                r.description,
                r.admin_notes,
                r.rejection_reason,
                r.status,
                r.created_at,
                r.updated_at,
                r.reviewed_at,
                COALESCE(u.full_name, "Deleted account") AS reporter_name
             FROM reports r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.id = ?
             LIMIT 1'
        );
        $detailStatement->execute([(int) $detailId]);
        $reportDetail = $detailStatement->fetch() ?: null;
        if (!$reportDetail) {
            $detailError = 'Report not found.';
        }
    }
}
>>>>>>> 56882bb9f493ac3e31ab078983b6cb59019105f4

require __DIR__ . '/../includes/header.php';

?>

<section class="page-hero">

    <span class="eyebrow">COMMUNITY SAFETY</span>
<<<<<<< HEAD

    <h1>Reported content</h1>

    <p>
        Review community reports and update their status.
    </p>

</section>


<section class="section-block">

    <?php if (!$reports): ?>

        <div class="empty-state">

            <h3>No reports yet</h3>

            <p>
                There are currently no reported items to review.
            </p>

        </div>

    <?php endif; ?>


    <?php foreach ($reports as $r): ?>

        <article class="account-card report-card">

            <div>

                <!-- Report information -->
                <div class="question-meta">

                    <span class="status-chip status-<?= e((string) $r['status']) ?>">

                        <?= e(ucfirst((string) $r['status'])) ?>

                    </span>

                    <span>
                        <?= e(ucfirst((string) $r['report_type'])) ?>
                        #<?= (int) ($r['reported_item_id'] ?? 0) ?>
                    </span>

                    <span>
                        <?= e(time_ago($r['created_at'])) ?>
                    </span>

                </div>


                <!-- Report subject -->
                <h3>
                    <?= e($r['subject']) ?>
                </h3>


                <!-- Report description -->
                <p>
                    <?= nl2br(e($r['description'])) ?>
                </p>


                <!-- Reporter -->
                <small>
                    Reported by <?= e($r['reporter']) ?>
                </small>


                <!-- Admin notes -->
                <?php if (!empty($r['admin_notes'])): ?>

                    <p>
                        <strong>Admin notes:</strong>
                        <?= nl2br(e($r['admin_notes'])) ?>
                    </p>

                <?php endif; ?>


                <!-- Rejection reason -->
                <?php if (!empty($r['rejection_reason'])): ?>

                    <p>
                        <strong>Rejection reason:</strong>
                        <?= nl2br(e($r['rejection_reason'])) ?>
                    </p>

                <?php endif; ?>

            </div>


            <!-- Report status update form -->
            <form method="post" class="report-status-form">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $r['id'] ?>"
                >


                <label for="status-<?= (int) $r['id'] ?>">
                    Report status
                </label>


                <select
                    id="status-<?= (int) $r['id'] ?>"
                    name="status"
                    required
                >

                    <option
                        value="pending"
                        <?= $r['status'] === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="reviewing"
                        <?= $r['status'] === 'reviewing' ? 'selected' : '' ?>
                    >
                        Reviewing
                    </option>

                    <option
                        value="resolved"
                        <?= $r['status'] === 'resolved' ? 'selected' : '' ?>
                    >
                        Resolved
                    </option>

                    <option
                        value="rejected"
                        <?= $r['status'] === 'rejected' ? 'selected' : '' ?>
                    >
                        Rejected
                    </option>

                </select>


                <button
                    class="btn btn-small btn-primary"
                    type="submit"
                >
                    Save
                </button>

            </form>

        </article>

    <?php endforeach; ?>
=======
    <h1>Report management</h1>
    <p>Review reports, record private investigation notes, and update outcomes.</p>
</section>

<?php require __DIR__ . '/admin-nav.php'; ?>

<section class="section-block">
    <div class="admin-grid">
        <article class="account-card admin-stat">
            <span>Total reports</span>
            <strong><?= number_format((int) $reportStats['total']) ?></strong>
        </article>
        <article class="account-card admin-stat">
            <span>Pending reports</span>
            <strong><?= number_format((int) $reportStats['pending']) ?></strong>
        </article>
        <article class="account-card admin-stat">
            <span>Under review</span>
            <strong><?= number_format((int) $reportStats['reviewing']) ?></strong>
        </article>
        <article class="account-card admin-stat">
            <span>Resolved reports</span>
            <strong><?= number_format((int) $reportStats['resolved']) ?></strong>
        </article>
        <article class="account-card admin-stat">
            <span>Rejected reports</span>
            <strong><?= number_format((int) $reportStats['rejected']) ?></strong>
        </article>
    </div>

    <?php if ($detailError !== ''): ?>
        <div class="flash flash-error" role="alert"><?= e($detailError) ?></div>
    <?php endif; ?>

    <?php if ($reportDetail): ?>
        <article class="account-card report-card" id="report-details">
            <div class="report-detail-content">
                <div class="question-meta">
                    <span class="status-chip status-<?= e($reportDetail['status']) ?>">
                        <?= e($reportStatuses[$reportDetail['status']] ?? $reportDetail['status']) ?>
                    </span>
                    <span>Report #<?= (int) $reportDetail['id'] ?></span>
                    <span><?= e(date('M j, Y g:i a', strtotime($reportDetail['created_at']))) ?></span>
                </div>

                <h2><?= e($reportDetail['subject']) ?></h2>
                <p><strong>Reporter:</strong> <?= e($reportDetail['reporter_name']) ?></p>
                <p><strong>Category:</strong> <?= e($reportTypes[$reportDetail['report_type']] ?? $reportDetail['report_type']) ?></p>
                <p><strong>Reported content ID:</strong> <?= $reportDetail['reported_item_id'] === null ? 'Not provided' : (int) $reportDetail['reported_item_id'] ?></p>
                <p><strong>Details:</strong></p>
                <p><?= nl2br(e($reportDetail['description'])) ?></p>

                <?php if ($reportDetail['rejection_reason'] !== null && $reportDetail['rejection_reason'] !== ''): ?>
                    <div class="form-note">
                        <strong>Rejection explanation:</strong>
                        <p><?= nl2br(e($reportDetail['rejection_reason'])) ?></p>
                    </div>
                <?php endif; ?>

                <p class="muted">
                    Submitted <?= e(date('M j, Y g:i a', strtotime($reportDetail['created_at']))) ?>
                    <?php if ($reportDetail['reviewed_at']): ?>
                        · Last reviewed <?= e(date('M j, Y g:i a', strtotime($reportDetail['reviewed_at']))) ?>
                    <?php endif; ?>
                </p>

                <h3>Internal investigation notes</h3>
                <p class="muted">These notes are visible only to administrators.</p>
                <form method="post" class="account-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $reportDetail['id'] ?>">
                    <input type="hidden" name="action" value="save_notes">
                    <textarea name="admin_notes" maxlength="10000" rows="5"><?= e($reportDetail['admin_notes'] ?? '') ?></textarea>
                    <button class="btn btn-primary" type="submit">Save internal notes</button>
                </form>

                <div class="report-action-group">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $reportDetail['id'] ?>">
                        <button class="btn btn-secondary" type="submit" name="action" value="review">
                            Mark under review
                        </button>
                        <button class="btn btn-primary" type="submit" name="action" value="resolve">
                            Mark resolved
                        </button>
                    </form>

                    <form method="post" class="account-form" onsubmit="return confirm('Reject this report with the explanation below?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $reportDetail['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <label for="rejection-reason">Rejection explanation</label>
                        <textarea
                            id="rejection-reason"
                            name="rejection_reason"
                            minlength="10"
                            maxlength="5000"
                            rows="3"
                            required
                        ></textarea>
                        <button class="btn btn-secondary" type="submit">Reject report</button>
                    </form>

                    <form method="post" onsubmit="return confirm('Permanently delete this report? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $reportDetail['id'] ?>">
                        <button class="btn btn-secondary" type="submit" name="action" value="delete">
                            Delete report
                        </button>
                    </form>
                </div>
            </div>
        </article>
>>>>>>> 56882bb9f493ac3e31ab078983b6cb59019105f4

        <p>
            <a class="btn btn-secondary" href="reports.php<?= e(buildPaginationUrl($pagination['page'], ['id' => null], 'page')) ?>">
                Back to report list
            </a>
        </p>
    <?php endif; ?>

    <form class="admin-search" method="get" action="reports.php">
        <label for="report-search">Search reports</label>
        <input
            id="report-search"
            type="search"
            name="q"
            value="<?= e($search) ?>"
            maxlength="120"
            placeholder="Search ID, reporter, subject, or details"
        >

        <label for="report-category">Category</label>
        <select id="report-category" name="category">
            <option value="">All categories</option>
            <?php foreach ($reportTypes as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $category === $key ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="report-status">Status</label>
        <select id="report-status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($reportStatuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
        <button class="btn btn-primary" type="submit">Search and filter</button>
        <a class="btn btn-secondary" href="reports.php">Clear filters</a>
    </form>

    <div class="account-card table-card">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Reporter</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Content ID</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reports): ?>
                        <tr>
                            <td colspan="8">No reports match the selected search and filters.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>#<?= (int) $report['id'] ?></td>
                            <td><?= e($report['reporter_name']) ?></td>
                            <td><?= e($reportTypes[$report['report_type']] ?? $report['report_type']) ?></td>
                            <td>
                                <a href="reports.php<?= e(buildPaginationUrl($pagination['page'], ['id' => (int) $report['id']], 'page')) ?>">
                                    <?= e($report['subject']) ?>
                                </a>
                            </td>
                            <td><?= $report['reported_item_id'] === null ? 'Not provided' : (int) $report['reported_item_id'] ?></td>
                            <td><?= e(date('M j, Y', strtotime($report['created_at']))) ?></td>
                            <td>
                                <span class="status-chip status-<?= e($report['status']) ?>">
                                    <?= e($reportStatuses[$report['status']] ?? $report['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="report-action-group">
                                    <a class="btn btn-small btn-secondary" href="reports.php<?= e(buildPaginationUrl($pagination['page'], ['id' => (int) $report['id']], 'page')) ?>">
                                        View details
                                    </a>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
                                        <button class="btn btn-small btn-secondary" type="submit" name="action" value="review">
                                            Under review
                                        </button>
                                        <button class="btn btn-small btn-primary" type="submit" name="action" value="resolve">
                                            Resolve
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= renderPagination($pagination, 'page', 'per_page', 'Reports') ?>
</section>


<?php require __DIR__ . '/../includes/footer.php'; ?>
