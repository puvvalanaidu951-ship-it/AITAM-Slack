
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'Admin dashboard';

// Fetch dashboard statistics
$stats = [

    'Members' => (int) db()->query(
        'SELECT COUNT(*) FROM users'
    )->fetchColumn(),

    'Active members' => (int) db()->query(
        'SELECT COUNT(*) FROM users WHERE status = "active"'
    )->fetchColumn(),

    'Questions' => (int) db()->query(
        'SELECT COUNT(*) FROM questions'
    )->fetchColumn(),

    'Answers' => (int) db()->query(
        'SELECT COUNT(*) FROM answers'
    )->fetchColumn(),

    'Open reports' => (int) db()->query(
        'SELECT COUNT(*)
         FROM reports
         WHERE status IN ("open", "reviewing")'
    )->fetchColumn(),

    'Pending review' => (int) db()->query(
        'SELECT
            (
                SELECT COUNT(*)
                FROM questions
                WHERE status = "pending_review"
                   OR moderation_status IN ("pending", "review")
            )
            +
            (
                SELECT COUNT(*)
                FROM answers
                WHERE status = "pending_review"
            )'
    )->fetchColumn(),

];

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">

    <span class="eyebrow">CONTROL CENTER</span>

    <h1>Admin dashboard</h1>

    <p>
        Manage community content, reports, member access
        and campus updates.
    </p>

</section>

<section class="section-block">

    <!-- Dashboard statistics -->
    <div class="admin-grid">

        <?php foreach ($stats as $label => $value): ?>

            <article class="account-card admin-stat">

                <span><?= e($label) ?></span>

                <strong><?= number_format($value) ?></strong>

            </article>

        <?php endforeach; ?>

    </div>


    <!-- Recent questions section -->
    <div class="section-heading compact">

        <div>
            <h2>Recent questions</h2>

            <p>Latest community posts.</p>
        </div>

    </div>

    <!-- Recent questions table -->
    <div class="account-card table-card">

        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Status</th>
                        <th>Moderation</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $questions = db()->query(
                        'SELECT
                            id,
                            title,
                            status,
                            moderation_status,
                            created_at
                         FROM questions
                         ORDER BY created_at DESC
                         LIMIT 10'
                    );

                    foreach ($questions as $q):
                    ?>

                        <tr>

                            <td>
                                <a href="../question.php?id=<?= (int) $q['id'] ?>">
                                    <?= e($q['title']) ?>
                                </a>
                            </td>

                            <td>
                                <span class="status-chip status-<?= e(
                                    (string) $q['status']
                                ) ?>">
                                    <?= e($q['status']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status-chip status-<?= e(
                                    (string) $q['moderation_status']
                                ) ?>">
                                    <?= e($q['moderation_status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(time_ago($q['created_at'])) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
