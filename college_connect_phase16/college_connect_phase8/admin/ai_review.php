
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'AI review';

// Handle AI insight review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    $action = (string) ($_POST['action'] ?? '');

    if (
        $id > 0 &&
        in_array($action, ['accepted', 'dismissed'], true)
    ) {
        db()->prepare(
            'UPDATE ai_question_insights
             SET review_status = ?,
                 reviewed_by = ?,
                 reviewed_at = NOW()
             WHERE id = ?'
        )->execute([
            $action,
            (int) $admin['id'],
            $id
        ]);

        set_flash('success', 'AI suggestion review status saved.');
    }

    redirect('ai_review.php');
}

// Fetch AI question insights
$rows = db()->query(
    'SELECT
        i.*,
        q.title,
        q.body,
        q.status AS question_status,
        c.name AS suggested_category,
        d.title AS duplicate_title
     FROM ai_question_insights i
     JOIN questions q
        ON q.id = i.question_id
     LEFT JOIN categories c
        ON c.id = i.suggested_category_id
     LEFT JOIN questions d
        ON d.id = i.duplicate_question_id
     ORDER BY
        (i.risk_level = "review") DESC,
        (i.review_status = "pending") DESC,
        i.created_at DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">

    <span class="eyebrow">AI-ASSISTED REVIEW</span>

    <h1>AI question insights</h1>

    <p>
        Rule-based suggestions for category, similar questions,
        and risk patterns. Review everything before acting.
    </p>

</section>

<section class="section-block">

    <div class="flash flash-info">
        This is a local heuristic prototype, not a connected
        generative AI model. A low risk result does not guarantee
        content is safe.
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state">
            <h3>No AI insights yet</h3>
            <p>
                New questions submitted after Phase 5 migration
                will appear here.
            </p>
        </div>
    <?php endif; ?>

    <?php foreach ($rows as $r): ?>

        <?php
        $flags = json_decode(
            (string) $r['risk_flags_json'],
            true
        ) ?: [];
        ?>

        <article class="account-card ai-review-card">

            <!-- Review status and risk information -->
            <div class="question-meta">

                <span class="status-chip status-<?= e(
                    (string) $r['review_status']
                ) ?>">
                    <?= e($r['review_status']) ?>
                </span>

                <span class="status-chip">
                    <?= e(
                        $r['risk_level'] === 'review'
                            ? 'Risk review'
                            : 'No configured risk pattern'
                    ) ?>
                </span>

                <span>
                    Confidence
                    <?= number_format(
                        (float) $r['classification_confidence'] * 100,
                        0
                    ) ?>%
                </span>

            </div>

            <!-- Question details -->
            <h2><?= e($r['title']) ?></h2>

            <p>
                <?= e(shorten($r['body'], 420)) ?>
            </p>

            <!-- AI suggestions -->
            <div class="ai-insight-grid">

                <div>
                    <strong>Suggested category</strong>

                    <p>
                        <?= e(
                            $r['suggested_category']
                                ?? 'No category suggestion'
                        ) ?>
                    </p>
                </div>

                <div>
                    <strong>Duplicate candidate</strong>

                    <p>
                        <?php if ($r['duplicate_question_id']): ?>

                            <a href="../question.php?id=<?= (int) $r['duplicate_question_id'] ?>">
                                <?= e(
                                    $r['duplicate_title']
                                        ?? (
                                            'Question #' .
                                            $r['duplicate_question_id']
                                        )
                                ) ?>
                            </a>

                            (
                            <?= number_format(
                                (float) $r['duplicate_score'] * 100,
                                0
                            ) ?>% title similarity
                            )

                        <?php else: ?>

                            No likely duplicate found

                        <?php endif; ?>
                    </p>
                </div>

            </div>

            <!-- Risk flags -->
            <div class="ai-risk">

                <strong>Risk flags:</strong>

                <?= e(
                    $flags
                        ? implode(', ', $flags)
                        : 'None detected'
                ) ?>

                <p>
                    <?= e($r['risk_reason']) ?>
                </p>

            </div>

            <!-- Suggested answer template -->
            <details>
                <summary>Suggested answer template</summary>

                <p>
                    <?= e(
                        $r['suggested_answer']
                            ?? 'No suggestion available'
                    ) ?>
                </p>
            </details>

            <!-- Open the original question -->
            <p>
                <a href="../question.php?id=<?= (int) $r['question_id'] ?>">
                    Open question ↗
                </a>
            </p>

            <!-- Review action buttons -->
            <?php if ($r['review_status'] === 'pending'): ?>

                <div class="moderation-buttons">

                    <?php
                    foreach (
                        [
                            'accepted' => 'Mark reviewed',
                            'dismissed' => 'Dismiss suggestion'
                        ] as $action => $label
                    ):
                    ?>

                        <form method="post">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $r['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="<?= e($action) ?>"
                            >

                            <button
                                class="btn btn-small btn-secondary"
                                type="submit"
                            >
                                <?= e($label) ?>
                            </button>

                        </form>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </article>

    <?php endforeach; ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
