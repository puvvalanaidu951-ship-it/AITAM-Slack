
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'Reports';

// Handle report status updates.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    ) ?: 0;

    $status = (string) ($_POST['status'] ?? '');

    $allowedStatuses = [
        'reviewing',
        'resolved',
        'dismissed'
    ];

    if ($id && in_array($status, $allowedStatuses, true)) {
        db()->prepare(
            'UPDATE reports
             SET status = ?, reviewed_by = ?
             WHERE id = ?'
        )->execute([
            $status,
            (int) $admin['id'],
            $id
        ]);

        set_flash('success', 'Report updated.');
    }

    redirect('reports.php');
}

// Fetch the latest reports.
$reports = db()->query(
    'SELECT
        r.*,
        COALESCE(u.full_name, "Community member") AS reporter
     FROM reports r
     LEFT JOIN users u ON u.id = r.reporter_id
     ORDER BY
        FIELD(
            r.status,
            "open",
            "reviewing",
            "resolved",
            "dismissed"
        ),
        r.created_at DESC
     LIMIT 200'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">COMMUNITY SAFETY</span>

    <h1>Reported content</h1>

    <p>Review reports and update their status.</p>
</section>

<section class="section-block">

    <?php if (!$reports): ?>
        <div class="empty-state">
            <h3>No reports yet</h3>
        </div>
    <?php endif; ?>

    <?php foreach ($reports as $r): ?>
        <article class="account-card report-card">

            <div>
                <div class="question-meta">

                    <span class="status-chip status-<?= e((string) $r['status']) ?>">
                        <?= e($r['status']) ?>
                    </span>

                    <span>
                        <?= e($r['content_type']) ?>
                        #<?= (int) $r['content_id'] ?>
                    </span>

                    <span>
                        <?= e(time_ago($r['created_at'])) ?>
                    </span>

                </div>

                <h3>
                    <?= e($r['reason']) ?>
                </h3>

                <p>
                    <?= nl2br(e($r['details'] ?? '')) ?>
                </p>

                <small>
                    Reported by <?= e($r['reporter']) ?>
                </small>
            </div>

            <form method="post" class="report-status-form">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $r['id'] ?>"
                >

                <label for="status-<?= (int) $r['id'] ?>">
                    Status
                </label>

                <select
                    id="status-<?= (int) $r['id'] ?>"
                    name="status"
                >
                    <option value="reviewing">
                        Reviewing
                    </option>

                    <option value="resolved">
                        Resolved
                    </option>

                    <option value="dismissed">
                        Dismissed
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
