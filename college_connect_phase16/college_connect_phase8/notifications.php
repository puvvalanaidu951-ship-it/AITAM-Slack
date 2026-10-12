<?php

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'read_all') {
        db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?'
        )->execute([(int) $user['id']]);

    } elseif ($action === 'read') {
        $id = filter_input(
            INPUT_POST,
            'notification_id',
            FILTER_VALIDATE_INT
        ) ?: 0;

        db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        )->execute([$id, (int) $user['id']]);
    }

    redirect('notifications.php');
}

$s = db()->prepare(
    'SELECT *
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 100'
);

$s->execute([(int) $user['id']]);
$items = $s->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">YOUR COMMUNITY</span>
    <h1>Notifications</h1>
    <p>Stay up to date when people respond to your questions.</p>
</section>

<section class="section-block">
    <div class="section-heading compact">
        <div>
            <h2>Recent activity</h2>
            <p>
                <?= count(array_filter(
                    $items,
                    fn($n) => (int) $n['is_read'] === 0
                )) ?>
                unread notifications
            </p>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="read_all">
            <button class="btn btn-secondary btn-small" type="submit">
                Mark all as read
            </button>
        </form>
    </div>

    <?php if (!$items): ?>
        <div class="empty-state">
            <h3>You're all caught up</h3>
            <p>New replies and accepted-answer updates will appear here.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($items as $n): ?>
                <article class="account-card notification-item <?= empty($n['is_read']) ? 'notification-unread' : '' ?>">
                    <div>
                        <h3><?= e($n['title']) ?></h3>
                        <p><?= e($n['message']) ?></p>
                        <small><?= e(time_ago($n['created_at'])) ?></small>
                    </div>

                    <div class="notification-actions">
                        <?php if ($n['related_type'] === 'question' && $n['related_id']): ?>
                            <a
                                class="btn btn-small btn-secondary"
                                href="question.php?id=<?= (int) $n['related_id'] ?>"
                            >
                                View
                            </a>
                        <?php endif; ?>

                        <?php if (!$n['is_read']): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="read">
                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?= (int) $n['id'] ?>"
                                >
                                <button
                                    class="btn btn-small btn-secondary"
                                    type="submit"
                                >
                                    Mark read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>