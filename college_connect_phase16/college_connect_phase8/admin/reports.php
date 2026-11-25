
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);

$pageTitle = 'Reports';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    ) ?: 0;

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

    }

    redirect('reports.php');
}

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

require __DIR__ . '/../includes/header.php';

?>

<section class="page-hero">

    <span class="eyebrow">COMMUNITY SAFETY</span>

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

</section>


<?php require __DIR__ . '/../includes/footer.php'; ?>
