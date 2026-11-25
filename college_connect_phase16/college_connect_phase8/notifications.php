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

$notificationParams = [':notification_user_id' => (int) $user['id']];
$unreadStatement = db()->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = :notification_user_id
       AND is_read = 0'
);
$unreadStatement->execute($notificationParams);
$unreadCount = (int) $unreadStatement->fetchColumn();
$notificationResult = getPaginatedRecords(
    'SELECT COUNT(*) FROM notifications WHERE user_id = :notification_user_id',
    'SELECT id, type, title, message, related_type, related_id, is_read, created_at
     FROM notifications
     WHERE user_id = :notification_user_id
     ORDER BY created_at DESC, id DESC
     LIMIT :pagination_limit OFFSET :pagination_offset',
    $notificationParams,
    15,
    [10, 15, 20, 50, 100]
);
$items = $notificationResult['records'];
$pagination = $notificationResult['pagination'];

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
                <?= $unreadCount ?>
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
    <?= renderPagination($pagination, 'page', 'per_page', 'Notifications') ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>