<?php

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$bookmarkParams = [':bookmark_user_id' => (int) $user['id']];
$bookmarkWhere = 'b.user_id = :bookmark_user_id
       AND q.status = "published"
       AND q.moderation_status = "allowed"';
$bookmarkResult = getPaginatedRecords(
    'SELECT COUNT(*)
     FROM question_bookmarks b
     JOIN questions q ON q.id = b.question_id
     WHERE ' . $bookmarkWhere,
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
     FROM question_bookmarks b
     JOIN questions q ON q.id = b.question_id
     JOIN categories c ON c.id = q.category_id
     LEFT JOIN users u ON u.id = q.user_id
     WHERE ' . $bookmarkWhere . '
     ORDER BY b.created_at DESC, b.id DESC
     LIMIT :pagination_limit OFFSET :pagination_offset',
    $bookmarkParams,
    10
);

$items = $bookmarkResult['records'];
$pagination = $bookmarkResult['pagination'];

$pageTitle = 'Saved questions';

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">YOUR LIBRARY</span>
    <h1>Saved questions</h1>
    <p>Keep useful campus answers close at hand.</p>
</section>

<section class="section-block">
    <div class="section-heading">
        <div>
            <h2>Your bookmarks</h2>
            <p><?= (int) $pagination['total'] ?> saved question(s)</p>
        </div>
        <a class="btn btn-primary" href="questions.php">Browse questions</a>
    </div>

    <?php if (!$items): ?>
        <div class="empty-state">
            <h3>No saved questions yet</h3>
            <p>Open a question and select “Save question” to keep it here.</p>
        </div>
    <?php else: ?>
        <div class="question-grid">
            <?php foreach ($items as $item): ?>
                <article class="account-card question-card">
                    <span class="topic-pill"><?= e($item['category_name']) ?></span>

                    <h3>
                        <a href="question.php?id=<?= (int) $item['id'] ?>">
                            <?= e($item['title']) ?>
                        </a>
                    </h3>

                    <p><?= e(shorten($item['body'], 180)) ?></p>

                    <div class="question-meta">
                        <span><?= e($item['author_name']) ?></span>
                        <span><?= (int) $item['answer_count'] ?> answers</span>
                    </div>

                    <a
                        class="btn btn-secondary btn-small"
                        href="question.php?id=<?= (int) $item['id'] ?>"
                    >Open question →</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= renderPagination($pagination, 'page', 'per_page', 'Saved questions') ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
